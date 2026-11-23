<?php

namespace App\Modules\Acceso;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

class AccesoController extends Controller
{
    public function registro(Request $r)
    {
        $d = $r->validate([
            'nombre' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'dni' => 'required|digits:8',
            'correo' => 'required|email|max:255|unique:usuarios,correo',
            'telefono' => 'nullable|string|max:30',
            'contrasena' => ['required', 'confirmed:contrasena_confirmacion', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);
        $u = DB::transaction(function () use ($d) {
            $p = Persona::where('dni', $d['dni'])->lockForUpdate()->first();
            abort_if($p && ($p->usuario || ! $p->cliente), 409, 'El DNI ya está vinculado.');
            $p ??= Persona::create([
                'dni' => $d['dni'],
                'nombre' => $d['nombre'],
                'apellido_paterno' => $d['apellido_paterno'],
                'apellido_materno' => $d['apellido_materno'] ?? null,
                'telefono' => $d['telefono'] ?? null,
                'correo_contacto' => mb_strtolower($d['correo']),
            ]);
            Cliente::firstOrCreate(['id_persona' => $p->id], ['activo' => true]);
            $u = User::create([
                'correo' => mb_strtolower($d['correo']),
                'contrasena' => $d['contrasena'],
                'id_persona' => $p->id,
            ]);
            $u->roles()->attach(Rol::where('codigo', 'cliente')->value('id'));

            return $u->load(['persona.cliente', 'roles', 'empleado']);
        }, 3);

        return response()->json(['datos' => $u->perfil()], 201);
    }

    private function credenciales(Request $r): User
    {
        $d = $r->validate(['correo' => 'required|email', 'contrasena' => 'required|string']);
        $u = User::with(['persona.cliente', 'roles', 'empleado'])->where('correo', mb_strtolower($d['correo']))->first();
        $clienteActivo = ! $u?->es('cliente') || ($u->persona?->cliente?->activo ?? false);
        if (! $u || ! Hash::check($d['contrasena'], $u->contrasena) || ! $u->activo || ($u->empleado && ! $u->empleado->activo) || ! $clienteActivo) {
            throw ValidationException::withMessages(['correo' => ['Credenciales inválidas o cuenta inactiva.']]);
        }

        return $u;
    }

    public function iniciar(Request $r)
    {
        $u = $this->credenciales($r);
        Auth::guard('web')->login($u);
        $r->session()->regenerate();
        $r->session()->forget('mfa_usuario');
        $r->session()->put('version_sesion', $u->version_sesion);

        return ['datos' => $u->perfil(), 'requiere_segundo_factor' => $u->es('administracion') || (bool) $u->fecha_confirmacion_mfa];
    }

    public function cerrar(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();
    }

    public function token(Request $r)
    {
        $r->validate(['nombre_dispositivo' => 'required|string|max:100']);
        $u = $this->credenciales($r);
        abort_unless($u->es('cliente') && ! $u->empleado && $u->roles->count() === 1, 403, 'Solo clientes pueden usar tokens móviles.');

        return response()->json(['datos' => [
            'token' => $u->createToken($r->nombre_dispositivo, ['cliente:movil'], now()->addDays(config('milibrows.token_dias')))->plainTextToken,
            'tipo' => 'Bearer',
            'perfil' => $u->perfil(),
        ]], 201);
    }

    public function revocar(Request $r)
    {
        $token = $r->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 422, 'Se requiere un token móvil.');
        $token->delete();

        return response()->noContent();
    }

    public function solicitar(Request $r)
    {
        $r->validate(['correo' => 'required|email']);
        Password::sendResetLink(['correo' => mb_strtolower($r->correo), 'activo' => true]);

        return ['mensaje' => 'Si el correo está registrado y activo, recibirá un enlace temporal.'];
    }

    public function restablecer(Request $r)
    {
        $d = $r->validate([
            'correo' => 'required|email',
            'token' => 'required|string',
            'contrasena' => ['required', 'confirmed:contrasena_confirmacion', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);
        $status = DB::transaction(function () use ($d, $r) {
            DB::table('codigos_restablecimiento')->where('correo', mb_strtolower($d['correo']))->lockForUpdate()->first();

            return Password::reset([
                'correo' => mb_strtolower($d['correo']),
                'token' => $d['token'],
                'password' => $d['contrasena'],
                'password_confirmation' => $r->contrasena_confirmacion,
                'activo' => true,
            ], function (User $u, string $password) {
                DB::transaction(function () use ($u, $password) {
                    $actual = User::lockForUpdate()->findOrFail($u->id);
                    abort_unless($actual->activo && $actual->correo === $u->correo, 422, 'La cuenta cambió; solicite un enlace nuevo.');
                    $actual->forceFill(['contrasena' => $password, 'version_sesion' => $actual->version_sesion + 1])->save();
                    $actual->tokens()->delete();
                });
                event(new PasswordReset($u));
            });
        }, 3);
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['token' => ['El enlace es inválido o ha expirado.']]);
        }

        return ['mensaje' => 'Contraseña restablecida. Inicie sesión nuevamente.'];
    }

    public function activar(Request $r, EnableTwoFactorAuthentication $activar)
    {
        $r->validate(['contrasena' => 'required|string']);
        abort_unless(Hash::check($r->contrasena, $r->user()->contrasena), 422, 'Contraseña inválida.');
        DB::transaction(function () use ($r, $activar) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            abort_if($u->fecha_confirmacion_mfa, 409, 'El segundo factor ya está configurado.');
            $activar($u);
        });

        return ['mensaje' => 'Escanee el QR y confirme un código TOTP.'];
    }

    public function qr(Request $r)
    {
        abort_unless($r->user()->secreto_segundo_factor, 409, 'Primero active el segundo factor.');
        abort_if($r->user()->fecha_confirmacion_mfa, 409, 'El segundo factor ya está confirmado.');

        return response()->json(['datos' => ['svg' => $r->user()->twoFactorQrCodeSvg()]], 200, ['Cache-Control' => 'no-store']);
    }

    public function confirmar(Request $r, ConfirmTwoFactorAuthentication $confirmar)
    {
        $r->validate(['codigo' => 'required|string|digits:6']);
        $codes = DB::transaction(function () use ($r, $confirmar) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            abort_if($u->fecha_confirmacion_mfa, 409, 'El segundo factor ya está confirmado.');
            $confirmar($u, $r->codigo);

            return $u->recoveryCodes();
        });
        $r->session()->regenerate();
        $r->session()->put('mfa_usuario', $r->user()->id);

        return response()->json(['datos' => ['codigos_recuperacion' => $codes]], 200, ['Cache-Control' => 'no-store']);
    }

    public function verificar(Request $r, TwoFactorAuthenticationProvider $provider)
    {
        $r->validate([
            'codigo' => 'required_without:codigo_recuperacion|nullable|digits:6',
            'codigo_recuperacion' => 'required_without:codigo|nullable|string|max:100',
        ]);
        DB::transaction(function () use ($r, $provider) {
            $u = User::lockForUpdate()->findOrFail($r->user()->id);
            abort_unless($u->fecha_confirmacion_mfa, 409, 'Configure el segundo factor primero.');
            if ($r->filled('codigo_recuperacion')) {
                abort_unless(in_array($r->codigo_recuperacion, $u->recoveryCodes(), true), 422, 'Código inválido.');
                $u->replaceRecoveryCode($r->codigo_recuperacion);
            } else {
                abort_unless($provider->verify(Fortify::currentEncrypter()->decrypt($u->secreto_segundo_factor), $r->codigo), 422, 'Código inválido o ya utilizado.');
            }
        });
        $r->session()->regenerate();
        $r->session()->put('mfa_usuario', $r->user()->id);

        return ['mensaje' => 'Segundo factor verificado.'];
    }
}
