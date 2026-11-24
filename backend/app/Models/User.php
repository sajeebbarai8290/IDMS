<?php

namespace App\Models;

use App\Core\Models\User as CoreUser;

/**
 * Class User
 *
 * Proxy model pointing to sovereign Core User.
 * IDMS uses Keycloak IAM for identity; passwords are never stored in Laravel.
 *
 * @package App\Models
 */
class User extends CoreUser
{
    // Inherits passwordless Keycloak authentication and UUID management
}
