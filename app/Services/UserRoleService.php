<?php

namespace App\Services;

use App\Models\UserRoles;

class UserRoleService
{
    public static function can($action, $id, $useDefaultFallback = true)
    {
        $default = self::defaultRoles();
        $roles = self::get($id);

        return
            in_array($action, array_keys($roles)) ?
                $roles[$action] :
                (
                    $useDefaultFallback ?
                        $default[$action] :
                        false
                );
    }

    public static function get($id)
    {
        if ($roles = UserRoles::whereUserId($id)->first()) {
            return $roles->roles;
        }

        return self::defaultRoles();
    }

    public static function roleKeys()
    {
        return array_keys(self::defaultRoles());
    }

    public static function defaultRoles(): array
    {
        return [
            'account-force-private' => true,
            'account-ignore-follow-requests' => true,

            'can-view-public-feed' => true,
            'can-view-network-feed' => true,
            'can-view-discover' => true,
            'can-view-hashtag-feed' => false,

            'can-post' => true,
            'can-comment' => true,
            'can-like' => true,
            'can-share' => true,

            'can-follow' => false,
            'can-make-public' => false,

            'can-direct-message' => false,
            'can-use-stories' => false,
            'can-view-sensitive' => false,
            'can-bookmark' => false,
            'can-collections' => false,
            'can-federation' => false,
        ];
    }

    public static function mapInvite($id, $data = []): array
    {
        $roles = self::get($id);

        $map = [
            'account-force-private' => 'private',
            'account-ignore-follow-requests' => 'private',

            'can-view-public-feed' => 'discovery_feeds',
            'can-view-network-feed' => 'discovery_feeds',
            'can-view-discover' => 'discovery_feeds',
            'can-view-hashtag-feed' => 'discovery_feeds',

            'can-post' => 'post',
            'can-comment' => 'comment',
            'can-like' => 'like',
            'can-share' => 'share',

            'can-follow' => 'follow',
            'can-make-public' => '!private',

            'can-direct-message' => 'dms',
            'can-use-stories' => 'story',
            'can-view-sensitive' => '!hide_cw',
            'can-bookmark' => 'bookmark',
            'can-collections' => 'collection',
            'can-federation' => 'federation',
        ];

        foreach ($map as $key => $value) {
            if (! isset($data[$value]) && ! isset($data[substr($value, 1)])) {
                $map[$key] = false;

                continue;
            }
            $map[$key] = str_starts_with($value, '!') ? ! $data[substr($value, 1)] : $data[$value];
        }

        return $map;
    }

    /**
     * @return mixed[]
     */
    public static function mapActions($id, $data = []): array
    {
        $res = [];
        $map = [
            'account-force-private' => 'private',
            'account-ignore-follow-requests' => 'private',

            'can-view-public-feed' => 'discovery_feeds',
            'can-view-network-feed' => 'discovery_feeds',
            'can-view-discover' => 'discovery_feeds',
            'can-view-hashtag-feed' => 'discovery_feeds',

            'can-post' => 'post',
            'can-comment' => 'comment',
            'can-like' => 'like',
            'can-share' => 'share',

            'can-follow' => 'follow',
            'can-make-public' => '!private',

            'can-direct-message' => 'dms',
            'can-use-stories' => 'story',
            'can-view-sensitive' => '!hide_cw',
            'can-bookmark' => 'bookmark',
            'can-collections' => 'collection',
            'can-federation' => 'federation',
        ];

        foreach ($map as $key => $value) {
            if (! isset($data[$value]) && ! isset($data[substr($value, 1)])) {
                $res[$key] = false;

                continue;
            }
            $res[$key] = str_starts_with($value, '!') ? ! $data[substr($value, 1)] : $data[$value];
        }

        return $res;
    }
}
