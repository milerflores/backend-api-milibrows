<?php

namespace App\Modules\Agenda;

use Illuminate\Http\Resources\Json\JsonResource;

class CitaResource extends JsonResource
{
    public function toArray($request): array
    {
        $servicios = $this->relationLoaded('servicios')
            ? $this->servicios
            : $this->servicios()->with(['empleado.usuario.persona', 'nivel', 'descuentoManual.motivo'])->get();
        $cobros = $this->relationLoaded('cobros')
            ? $this->cobros
            : $this->cobros()->with('metodo')->get();

        $totalRealizado = (float) $servicios->where('estado_servicio', 'realizado')->sum('precio_acordado');
        $totalPagado = (float) $cobros->where('estado', 'registrado')->sum('importe');
        $saldo = max(0.0, round($totalRealizado - $totalPagado, 2));

        $inicio = $this->fechaHoraInicio();
        $fin = $this->fechaHoraFin();

        $d = [
            'id' => $this->id,
            'id_cliente' => $this->id_cliente,
            'fecha' => $this->fecha->format('Y-m-d'),
            'fecha_inicio' => $inicio?->toIso8601String(),
            'fecha_fin' => $fin?->toIso8601String(),
            'fecha_finalizacion' => $this->fecha_finalizacion?->toIso8601String(),
            'origen' => $this->origen,
            'estado' => $this->estado,
            'numero_revision' => $this->numero_revision,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'servicios' => $servicios->map(fn ($s) => [
                'id' => $s->id,
                'id_cita' => $s->id_cita,
                'id_servicio' => $s->id_servicio,
                'id_empleado' => $s->id_empleado,
                'estilista' => $s->empleado?->usuario?->persona?->nombre_completo,
                'id_nivel' => $s->id_nivel,
                'nombre_servicio_acordado' => $s->nombre_servicio_acordado,
                'precio_lista' => $s->precio_lista,
                'monto_descuento_automatico' => $s->monto_descuento_automatico,
                'precio_acordado' => $s->precio_acordado,
                'duracion_minutos' => $s->duracion_minutos,
                'hora_inicio' => substr((string) $s->hora_inicio, 0, 5),
                'fecha_inicio_real' => $s->fecha_inicio_real?->toIso8601String(),
                'fecha_finalizacion' => $s->fecha_finalizacion?->toIso8601String(),
                'estado_servicio' => $s->estado_servicio,
                'motivo_estado_servicio' => $s->motivo_estado_servicio,
                'descuento_manual' => $s->descuentoManual ? [
                    'monto' => $s->descuentoManual->monto,
                    'id_motivo_descuento' => $s->descuentoManual->id_motivo_descuento,
                    'motivo' => $s->descuentoManual->motivo?->nombre,
                    'nota' => $s->descuentoManual->nota,
                    'fecha_creacion' => $s->descuentoManual->fecha_creacion?->toIso8601String(),
                ] : null,
            ])->values(),
            'cobros' => $cobros->map(fn ($co) => [
                'id' => $co->id,
                'id_metodo_pago' => $co->id_metodo_pago,
                'metodo' => $co->metodo?->nombre,
                'importe' => $co->importe,
                'fecha_cobro' => $co->fecha_cobro?->toIso8601String(),
                'referencia_pago' => $co->referencia_pago,
                'estado' => $co->estado,
            ])->values(),
            'total_realizado' => number_format($totalRealizado, 2, '.', ''),
            'total_pagado' => number_format($totalPagado, 2, '.', ''),
            'saldo_pendiente' => number_format($saldo, 2, '.', ''),
        ];

        if ($request->user()->es('administracion', 'recepcion', 'estilista')) {
            $d['cliente'] = $this->cliente?->persona;
            $d['nota'] = $this->nota;
            $d['historial'] = $this->historial;
        }

        return $d;
    }
}
