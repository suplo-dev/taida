<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\RendersPublicOutput;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements RendersPublicOutput
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * A user surfaces in exactly one place on the public site: the byline of a
     * post (PostDetailResource). Someone who has not signed a live post yet can
     * change anything in their profile without moving a pixel.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->authoredPosts()->published()->exists();
    }

    /**
     * Only `name`. Passwords, e-mail addresses and roles never leave the CMS.
     *
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return ['name'];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function authoredPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
