<?php

namespace App\Modules\Contexts;

use App\Helpers\Depth;
use App\Helpers\MemberRole;
use App\Models\Connection;
use App\Models\Enterprise;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Modules\Memberships\MembershipQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

readonly class Context
{
    private const string subject = 'context.subject.';
    private const string standing = 'context.standing.';

    public static function bind(Request $Request, Depth $Depth, Model $Model): void
    {
        $Request->attributes->set(self::subject.$Depth->value, $Model);
    }

    public static function of(Depth $Depth): ?Model
    {
        $Model = request()->attributes->get(self::subject.$Depth->value);

        return $Model instanceof Model ? $Model : null;
    }

    public static function enterprise(): ?Enterprise
    {
        $Model = self::of(Depth::enterprise);

        return $Model instanceof Enterprise ? $Model : null;
    }

    public static function organization(): ?Organization
    {
        $Model = self::of(Depth::organization);

        return $Model instanceof Organization ? $Model : null;
    }

    public static function project(): ?Project
    {
        $Model = self::of(Depth::project);

        return $Model instanceof Project ? $Model : null;
    }

    public static function connection(): ?Connection
    {
        $Model = self::of(Depth::connection);

        return $Model instanceof Connection ? $Model : null;
    }

    /** The deepest depth the address settled. */
    public static function deepest(): ?Depth
    {
        $deepest = null;

        foreach (Depth::chain() as $Depth) {
            if (self::of($Depth) instanceof Model) {
                $deepest = $Depth;
            }
        }

        return $deepest;
    }

    /**
     * The standing that answers for one depth, resolved once for the request.
     *
     * Every surface asks this rather than the membership rows, so a page, its rail and
     * its trail cannot disagree about what the reader may do — and the answer is bound
     * beside the subject it belongs to, so asking twice costs one question.
     */
    public static function role(Depth $Depth): ?MemberRole
    {
        $Request = request();
        $key = self::standing.$Depth->value;

        if ($Request->attributes->has($key)) {
            $held = $Request->attributes->get($key);

            return $held instanceof MemberRole ? $held : null;
        }

        $Subject = self::of($Depth);
        $User = Auth::user();
        $MemberRole = null;

        if ($Subject instanceof Model && $User instanceof User) {
            $MemberRole = MembershipQuery::effective($Depth, $Subject, $User);
        }

        $Request->attributes->set($key, $MemberRole);

        return $MemberRole;
    }

    /** The standing that answers where the reader stands, which is the deepest depth holding one. */
    public static function standing(): ?MemberRole
    {
        foreach (array_reverse(Depth::chain()) as $Depth) {
            if (! $Depth->holdsMembers()) {
                continue;
            }

            if (self::of($Depth) instanceof Model) {
                return self::role($Depth);
            }
        }

        return null;
    }
}
