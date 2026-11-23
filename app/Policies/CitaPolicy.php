<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;

class CitaPolicy
{
    public function view(User $u, Cita $c): bool
    {
        if ($u->es('administracion', 'recepcion')) {
            return true;
        }
        if ($u->es('estilista') && $u->empleado) {
            $servicios = $c->relationLoaded('servicios') ? $c->servicios : $c->servicios()->get();
            if ($servicios->contains('id_empleado', $u->empleado->id)) {
                return true;
            }
        }

        return $u->es('cliente') && $u->idCliente() === $c->id_cliente;
    }

    public function update(User $u, Cita $c): bool
    {
        return $this->view($u, $c);
    }

    public function reprogramar(User $u, Cita $c): bool
    {
        return $u->es('administracion', 'recepcion', 'cliente') && $this->view($u, $c);
    }

    public function completar(User $u, Cita $c): bool
    {
        if ($u->es('administracion', 'recepcion')) {
            return true;
        }
        if ($u->es('estilista') && $u->empleado) {
            $servicios = $c->relationLoaded('servicios') ? $c->servicios : $c->servicios()->get();

            return $servicios->contains('id_empleado', $u->empleado->id);
        }

        return false;
    }
}
