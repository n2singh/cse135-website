<?php
// hw4/includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', '1');
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION["user"]) && is_array($_SESSION["user"]);
}

function current_user(): ?array {
    return $_SESSION["user"] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) {
        header("Location: /hw4/login.php");
        exit;
    }
}

function has_role(array $roles): bool {
    if (!is_logged_in()) {
        return false;
    }

    $role = $_SESSION["user"]["role"] ?? null;
    return $role !== null && in_array($role, $roles, true);
}

function require_role(array $roles): void {
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);
        include __DIR__ . "/../403.php";
        exit;
    }
}

function user_can_access_section(string $section): bool {
    if (!is_logged_in()) {
        return false;
    }

    $user = $_SESSION["user"];
    $role = $user["role"] ?? null;

    if ($role === "super_admin") {
        return true;
    }

    if ($role === "viewer") {
        return false;
    }

    $allowed = $user["allowed_sections"] ?? [];
    return is_array($allowed) && in_array($section, $allowed, true);
}

function require_section_access(string $section): void {
    require_login();

    if (!user_can_access_section($section)) {
        http_response_code(403);
        include __DIR__ . "/../403.php";
        exit;
    }
}
