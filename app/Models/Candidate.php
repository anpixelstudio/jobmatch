<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    protected $fillable = [
        'external_id',
        'source',
        'category',
        'name',
        'email',
        'phone',
        'location',
        'raw_text',
        'pdf_path',
        'original_filename',
        'parsed_json',
        'status',
        'uploaded_by',
        'job_opening_id',
        'review_status',
    ];

    protected function casts(): array
    {
        return [
            'parsed_json' => 'array',
        ];
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }

    public function displayName(): string
    {
        if ($this->hasCleanName()) {
            return (string) $this->name;
        }

        if ($this->source === 'kaggle') {
            $id = $this->external_id ?: (string) $this->id;

            return $this->shortCategoryLabel().' · #'.$id;
        }

        if ($this->original_filename) {
            return pathinfo($this->original_filename, PATHINFO_FILENAME);
        }

        return 'Kandidat #'.(string) $this->id;
    }

    public function shortCategoryLabel(): string
    {
        $map = [
            'INFORMATION-TECHNOLOGY' => 'IT',
            'HUMAN-RESOURCES' => 'HR',
            'HR' => 'HR',
            'FINANCE' => 'Finance',
            'ACCOUNTANT' => 'Accounting',
            'ENGINEERING' => 'Engineering',
            'SALES' => 'Sales',
            'DIGITAL-MEDIA' => 'Digital Media',
            'HEALTHCARE' => 'Healthcare',
            'BANKING' => 'Banking',
            'TEACHER' => 'Teaching',
            'DESIGNER' => 'Design',
            'CONSULTANT' => 'Consulting',
            'ADVOCATE' => 'Legal',
            'BPO' => 'BPO',
            'CHEF' => 'Chef',
            'FITNESS' => 'Fitness',
            'AGRICULTURE' => 'Agriculture',
            'AUTOMOBILE' => 'Automotive',
            'APPAREL' => 'Apparel',
            'ARTS' => 'Arts',
            'CONSTRUCTION' => 'Construction',
            'AVIATION' => 'Aviation',
        ];

        $key = strtoupper((string) $this->category);

        return $map[$key] ?? ($this->category
            ? str($this->category)->replace('-', ' ')->title()->toString()
            : 'CV');
    }

    public function hasCleanName(): bool
    {
        $name = trim((string) $this->name);
        if ($name === '' || strlen($name) < 3 || strlen($name) > 48) {
            return false;
        }

        if (str_ends_with($name, '.') || str_ends_with($name, ',')) {
            return false;
        }
        if (preg_match('/\b(initiatives?|summary|experience|skills|objective|responsible|managed)\b/i', $name)) {
            return false;
        }
        if (preg_match('/^(cv|resume)\b/i', $name)) {
            return false;
        }

        return (bool) preg_match(
            '/^[A-ZÀ-Ö][A-Za-zÀ-ö\'.\-]+(?:\s+[A-ZÀ-Ö][A-Za-zÀ-ö\'.\-]+){0,3}$/',
            $name
        );
    }
}
