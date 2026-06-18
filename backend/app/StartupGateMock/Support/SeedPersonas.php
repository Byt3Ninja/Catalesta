<?php

declare(strict_types=1);

namespace App\StartupGateMock\Support;

/**
 * Seed personas for the mock OIDC provider.
 *
 * Each persona represents a realistic user profile with scopes, roles, consents,
 * and flags that exercise different code paths in the platform adapter.
 */
final class SeedPersonas
{
    /**
     * Returns all 10 seed personas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::founderOnly(),
            self::founderAndMentor(),
            self::mentorOnly(),
            self::evaluator(),
            self::trainer(),
            self::serviceProvider(),
            self::orgAdmin(),
            self::revokedConsent(),
            self::incompleteProfile(),
            self::expiredRoleVerification(),
        ];
    }

    /**
     * Finds a persona by sub identifier.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $sub): ?array
    {
        foreach (self::all() as $persona) {
            if ($persona['sub'] === $sub) {
                return $persona;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Private persona factories
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private static function founderOnly(): array
    {
        return [
            'sub' => 'sg_user_01',
            'email' => 'founder.only@example.com',
            'email_verified' => true,
            'name' => 'Alex Founder',
            'locale' => 'en',
            'profile_updated_at' => 1_700_000_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.founder.read',
                'profile.startups.read',
            ],
            'profile' => [
                'bio' => 'Serial entrepreneur building in fintech.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_01.jpg',
                'location' => 'Nairobi, Kenya',
            ],
            'role_profiles' => [
                [
                    'role' => 'founder',
                    'verified' => true,
                    'verified_at' => '2024-01-15T10:00:00Z',
                ],
            ],
            'startups' => [
                [
                    'id' => 'startup_001',
                    'name' => 'FinEdge',
                    'stage' => 'seed',
                    'sector' => 'fintech',
                ],
            ],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2024-01-10T08:00:00Z',
                ],
                [
                    'scope' => 'profile.founder.read',
                    'granted_at' => '2024-01-10T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function founderAndMentor(): array
    {
        return [
            'sub' => 'sg_user_02',
            'email' => 'founder.mentor@example.com',
            'email_verified' => true,
            'name' => 'Jordan FounderMentor',
            'locale' => 'en',
            'profile_updated_at' => 1_700_100_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.founder.read',
                'profile.mentor.read',
                'profile.startups.read',
            ],
            'profile' => [
                'bio' => 'Founder turned mentor with exits in SaaS.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_02.jpg',
                'location' => 'Lagos, Nigeria',
            ],
            'role_profiles' => [
                [
                    'role' => 'founder',
                    'verified' => true,
                    'verified_at' => '2023-06-01T10:00:00Z',
                ],
                [
                    'role' => 'mentor',
                    'verified' => true,
                    'verified_at' => '2023-09-01T10:00:00Z',
                ],
            ],
            'startups' => [
                [
                    'id' => 'startup_002',
                    'name' => 'CloudFlow',
                    'stage' => 'series-a',
                    'sector' => 'saas',
                ],
            ],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2023-06-01T09:00:00Z',
                ],
                [
                    'scope' => 'profile.mentor.read',
                    'granted_at' => '2023-09-01T09:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function mentorOnly(): array
    {
        return [
            'sub' => 'sg_user_03',
            'email' => 'mentor.only@example.com',
            'email_verified' => true,
            'name' => 'Sam Mentor',
            'locale' => 'fr',
            'profile_updated_at' => 1_700_200_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.mentor.read',
            ],
            'profile' => [
                'bio' => 'Mentor specialising in go-to-market strategy.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_03.jpg',
                'location' => 'Dakar, Senegal',
            ],
            'role_profiles' => [
                [
                    'role' => 'mentor',
                    'verified' => true,
                    'verified_at' => '2024-02-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2024-02-01T08:00:00Z',
                ],
                [
                    'scope' => 'profile.mentor.read',
                    'granted_at' => '2024-02-01T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function evaluator(): array
    {
        return [
            'sub' => 'sg_user_04',
            'email' => 'evaluator@example.com',
            'email_verified' => true,
            'name' => 'Dana Evaluator',
            'locale' => 'en',
            'profile_updated_at' => 1_700_300_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.professional.read',
            ],
            'profile' => [
                'bio' => 'Evaluator for early-stage technology startups.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_04.jpg',
                'location' => 'Accra, Ghana',
            ],
            'role_profiles' => [
                [
                    'role' => 'evaluator',
                    'verified' => true,
                    'verified_at' => '2024-03-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2024-03-01T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function trainer(): array
    {
        return [
            'sub' => 'sg_user_05',
            'email' => 'trainer@example.com',
            'email_verified' => true,
            'name' => 'Riley Trainer',
            'locale' => 'en',
            'profile_updated_at' => 1_700_400_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.professional.read',
            ],
            'profile' => [
                'bio' => 'Trainer delivering lean-startup and product design workshops.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_05.jpg',
                'location' => 'Cape Town, South Africa',
            ],
            'role_profiles' => [
                [
                    'role' => 'trainer',
                    'verified' => true,
                    'verified_at' => '2024-04-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2024-04-01T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function serviceProvider(): array
    {
        return [
            'sub' => 'sg_user_06',
            'email' => 'service.provider@example.com',
            'email_verified' => true,
            'name' => 'Morgan ServiceProvider',
            'locale' => 'en',
            'profile_updated_at' => 1_700_500_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.service_provider.read',
            ],
            'profile' => [
                'bio' => 'Legal and accounting services for startups.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_06.jpg',
                'location' => 'Johannesburg, South Africa',
            ],
            'role_profiles' => [
                [
                    'role' => 'service_provider',
                    'verified' => true,
                    'verified_at' => '2024-05-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2024-05-01T08:00:00Z',
                ],
                [
                    'scope' => 'profile.service_provider.read',
                    'granted_at' => '2024-05-01T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function orgAdmin(): array
    {
        return [
            'sub' => 'sg_user_07',
            'email' => 'org.admin@example.com',
            'email_verified' => true,
            'name' => 'Casey OrgAdmin',
            'locale' => 'en',
            'profile_updated_at' => 1_700_600_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.professional.read',
            ],
            'profile' => [
                'bio' => 'Organisation administrator managing incubator programs.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_07.jpg',
                'location' => 'Kigali, Rwanda',
            ],
            'role_profiles' => [
                [
                    'role' => 'org_admin',
                    'verified' => true,
                    'verified_at' => '2024-06-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2024-06-01T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function revokedConsent(): array
    {
        return [
            'sub' => 'sg_user_08',
            'email' => 'revoked.consent@example.com',
            'email_verified' => true,
            'name' => 'Taylor Revoked',
            'locale' => 'en',
            'profile_updated_at' => 1_700_700_000,
            'granted_scopes' => ['openid'],
            'profile' => [
                'bio' => '',
                'avatar_url' => null,
                'location' => null,
            ],
            'role_profiles' => [
                [
                    'role' => 'founder',
                    'verified' => true,
                    'verified_at' => '2023-01-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [],
            'consent_revoked' => true,
            'incomplete' => false,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function incompleteProfile(): array
    {
        return [
            'sub' => 'sg_user_09',
            'email' => 'incomplete.profile@example.com',
            'email_verified' => false,
            'name' => '',
            'locale' => 'en',
            'profile_updated_at' => 1_700_800_000,
            'granted_scopes' => ['openid'],
            'profile' => [
                'bio' => null,
                'avatar_url' => null,
                'location' => null,
            ],
            'role_profiles' => [],
            'startups' => [],
            'consents' => [],
            'consent_revoked' => false,
            'incomplete' => true,
            'role_verification_expired' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function expiredRoleVerification(): array
    {
        return [
            'sub' => 'sg_user_10',
            'email' => 'expired.role@example.com',
            'email_verified' => true,
            'name' => 'Pat ExpiredRole',
            'locale' => 'en',
            'profile_updated_at' => 1_700_900_000,
            'granted_scopes' => [
                'openid',
                'profile.basic.read',
                'profile.mentor.read',
            ],
            'profile' => [
                'bio' => 'Mentor whose role verification has lapsed.',
                'avatar_url' => 'https://cdn.example.com/avatars/sg_user_10.jpg',
                'location' => 'Kampala, Uganda',
            ],
            'role_profiles' => [
                [
                    'role' => 'mentor',
                    'verified' => false,
                    'verified_at' => '2022-01-01T10:00:00Z',
                    'expired_at' => '2023-01-01T10:00:00Z',
                ],
            ],
            'startups' => [],
            'consents' => [
                [
                    'scope' => 'profile.basic.read',
                    'granted_at' => '2022-01-01T08:00:00Z',
                ],
            ],
            'consent_revoked' => false,
            'incomplete' => false,
            'role_verification_expired' => true,
        ];
    }
}
