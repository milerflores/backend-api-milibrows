<?php

use Laravel\Fortify\Features;

return ['guard' => 'web', 'passwords' => 'users', 'username' => 'correo', 'email' => 'correo', 'views' => false,
    'features' => [Features::resetPasswords(), Features::twoFactorAuthentication(['confirm' => true])]];
