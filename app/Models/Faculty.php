<?php

namespace App\Models;

use Database\Factories\FacultyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Faculty extends Model
{
    /** @use HasFactory<FacultyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Faculties → Departments (the academic hierarchy that replaces "organizations").
     *
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasManyThrough<Course, Department, $this>
     */
    public function courses(): HasManyThrough
    {
        return $this->hasManyThrough(Course::class, Department::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
