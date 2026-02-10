<?php
declare(strict_types=1);

namespace App\Database;

final class SkillTemplates
{
    private static array $templates = [
        'Ultimate Frisbee' => [
            'Physique' => [
                'Vitesse',
                'Endurance',
                'Agilité / changement de direction',
                'Hauteur / saut',
            ],
            'Lancer' => [
                'Coup droit (forehand)',
                'Revers (backhand)',
                'Hammer / aérien',
                'Précision',
                'Puissance / distance',
            ],
            'Défensif' => [
                'Positionnement forcé',
                'Marquage individuel (man-to-man)',
                'Aide défensive (poach/switch)',
                'Bloc / contest',
                'Lecture du jeu adverse',
            ],
            'Offensif' => [
                'Cuts / création d\'espace',
                'Jeu de handlers (reset, break)',
                'Jeu de cutters (deep, under)',
                'Synchronisme / timing',
                'Endzone / finition',
            ],
            'Mental / Attitude' => [
                'Écoute et application des consignes',
                'Communication sur le terrain',
                'Esprit d\'équipe',
                'Résilience / gestion d\'erreur',
            ],
        ],
        'Hockey' => [
            'Physique' => [
                'Vitesse',
                'Accélération',
                'Changement de direction',
                'Endurance',
                'Hauteur',
            ],
            'Lancer' => [
                'Coup droit',
                'Revers',
            ],
            'Défensif' => [
                'Positionnement',
                'Repositionnement',
                'Prise d\'information',
                'Anticipation',
                'Prise de décision',
            ],
            'Offensif' => [
                'Position',
                'Ajustement au déplacement de jeu',
                'Synchronisme',
                'Création d\'espace',
                'Prise d\'espace',
            ],
            'Psychologique' => [
                'Écoute',
                'Applique les consignes',
                'Adaptabilité',
            ],
        ],
    ];

    public static function getSports(): array
    {
        return array_keys(self::$templates);
    }

    public static function getTemplate(string $sport): ?array
    {
        return self::$templates[$sport] ?? null;
    }
}
