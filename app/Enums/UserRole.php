<?php

namespace App\Enums;

enum UserRole: string
{
    case Citoyen = 'citoyen';
    case Gestionnaire = 'gestionnaire';
    case Admin = 'admin';

    /**
     * Libellé lisible du rôle.
     */
    public function label(): string
    {
        return match ($this) {
            self::Citoyen => 'Citoyen',
            self::Gestionnaire => 'Gestionnaire',
            self::Admin => 'Administrateur',
        };
    }

    /**
     * Classes Tailwind d'un badge de rôle (design system du Back Office).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Citoyen => 'bg-blue-light-50 text-blue-light-700 ring-blue-light-600/20 dark:bg-blue-light-500/15 dark:text-blue-light-400 dark:ring-blue-light-500/20',
            self::Gestionnaire => 'bg-brand-50 text-brand-700 ring-brand-600/20 dark:bg-brand-500/15 dark:text-brand-400 dark:ring-brand-500/20',
            self::Admin => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-500/15 dark:text-warning-400 dark:ring-warning-500/20',
        };
    }

    /**
     * Rôles autorisés à accéder au Back Office.
     *
     * @return list<string>
     */
    public static function backOfficeValues(): array
    {
        return [self::Gestionnaire->value, self::Admin->value];
    }

    /**
     * Tous les rôles sous forme [valeur => libellé].
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
