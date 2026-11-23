<?php

namespace App\Modules\Fidelizacion;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\ConfiguracionEstudio;
use App\Models\MovimientoPuntos;
use App\Models\NivelFidelizacion;
use App\Models\ServicioCita;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FidelizacionService
{
    public function saldo(int $idCliente): int
    {
        return (int) MovimientoPuntos::where('id_cliente', $idCliente)->sum('puntos');
    }

    public function nivelActual(int $idCliente): NivelFidelizacion
    {
        $saldo = max(0, $this->saldo($idCliente));
        $activos = NivelFidelizacion::where('activo', true)
            ->orderByDesc('puntos_minimos')
            ->orderByDesc('orden')
            ->get();

        return $activos->first(fn (NivelFidelizacion $n) => $n->puntos_minimos <= $saldo)
            ?? NivelFidelizacion::where('codigo', 'glow')->firstOrFail();
    }

    public function cotizarServicio(int $idCliente, string|float $precioLista): array
    {
        $nivel = $this->nivelActual($idCliente);
        $lista = round((float) $precioLista, 2);
        $descuento = round($lista * ((float) $nivel->porcentaje_descuento / 100), 2);
        $descuento = min($descuento, $lista);
        $acordado = max(0.0, round($lista - $descuento, 2));

        return [
            'nivel' => $nivel,
            'precio_lista' => number_format($lista, 2, '.', ''),
            'monto_descuento_automatico' => number_format($descuento, 2, '.', ''),
            'precio_acordado' => number_format($acordado, 2, '.', ''),
        ];
    }

    public function ajustar(User $u, array $d): MovimientoPuntos
    {
        $motivo = trim((string) $d['motivo']);
        abort_if($motivo === '', 422, 'Indique un motivo para el ajuste de puntos.');

        return DB::transaction(function () use ($u, $d, $motivo) {
            Cliente::lockForUpdate()->findOrFail($d['id_cliente']);

            return MovimientoPuntos::create([
                'id_cliente' => $d['id_cliente'],
                'id_cobro' => null,
                'id_usuario_creador' => $u->id,
                'tipo' => 'ajuste',
                'puntos' => (int) $d['puntos'],
                'motivo' => $motivo,
                'fecha_creacion' => now(),
            ]);
        }, 3);
    }

    public function resumen(int $idCliente): array
    {
        $saldo = $this->saldo($idCliente);
        $nivelesActivos = NivelFidelizacion::where('activo', true)->orderBy('orden')->get();
        $nivel = $nivelesActivos->filter(fn ($n) => $n->puntos_minimos <= max(0, $saldo))->last()
            ?? NivelFidelizacion::where('codigo', 'glow')->firstOrFail();
        $siguiente = $nivelesActivos->first(fn ($n) => $n->puntos_minimos > max(0, $saldo));

        $servicios = ServicioCita::where('estado_servicio', 'realizado')
            ->whereHas('cita', fn ($q) => $q->where('id_cliente', $idCliente));
        $numero = (clone $servicios)->count();
        $visitas = (clone $servicios)->distinct()->count('id_cita');
        $pagado = Cobro::whereHas('cita', fn ($q) => $q->where('id_cliente', $idCliente))
            ->where('estado', 'registrado')
            ->sum('importe');

        $movimientos = MovimientoPuntos::where('id_cliente', $idCliente)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'puntos' => $saldo,
            'monto_por_punto' => ConfiguracionEstudio::findOrFail(1)->monto_por_punto,
            'nivel' => $nivel,
            'siguiente_nivel' => $siguiente ? [
                'id' => $siguiente->id,
                'codigo' => $siguiente->codigo,
                'nombre' => $siguiente->nombre,
                'puntos_minimos' => $siguiente->puntos_minimos,
                'porcentaje_descuento' => $siguiente->porcentaje_descuento,
                'puntos_faltantes' => max(0, $siguiente->puntos_minimos - max(0, $saldo)),
            ] : null,
            'servicios_realizados' => $numero,
            'visitas' => $visitas,
            'monto_pagado' => number_format((float) $pagado, 2, '.', ''),
            'movimientos' => $movimientos,
        ];
    }
}
