<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'jobs';

    const CREATED_AT = 'date';

    const UPDATED_AT = 'updated_at';

    // Status constants
    const STATUS_DRAFT = 1;

    const STATUS_PENDING = 2;

    const STATUS_PUBLISHED = 3;

    const STATUS_EXPIRED = 4;

    const STATUS_TRASHED = 5;

    const STATUS_QUOTA_FULL = 6;

    protected $fillable = [
        'job_no', 'title', 'company_name', 'description',
        'job_category_id', 'prefecture_id', 'area_id', 'station', 'address',
        'japanese_level', 'working_hours', 'working_days',
        'wage', 'wage_type_id', 'wage_detail',
        'trans_exp_id', 'transportation_detail', 'benefits', 'requirement',
        'img_path', 'img_name', 'img_ext', 'img_id',
        'lang_id', 'user_id', 'job_status_id',
        'apply_link', 'img_link', 'featured', 'send_email',
        'updated_by', 'Expire_Date', 'delete_at',
    ];

    protected $casts = [
        'date' => 'datetime',
        'updated_at' => 'datetime',
        'Expire_Date' => 'datetime',
        'delete_at' => 'datetime',
        'featured' => 'boolean',
        'send_email' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'job_category_id');
    }

    public function prefecture()
    {
        return $this->belongsTo(Prefecture::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class, 'lang_id');
    }

    public function wageType()
    {
        return $this->belongsTo(WageType::class);
    }

    public function transExpPayment()
    {
        return $this->belongsTo(TransExpPayment::class, 'trans_exp_id');
    }

    public function image()
    {
        return $this->belongsTo(Image::class, 'img_id');
    }

    public function status()
    {
        return $this->belongsTo(JobStatus::class, 'job_status_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class, 'job_no', 'job_no');
    }

    public function appliedByUsers()
    {
        return $this->hasMany(JobApplied::class);
    }

    public function secondaryApplies()
    {
        return $this->hasMany(SecondaryApply::class, 'job_no', 'job_no');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'job_tag', 'job_id', 'tag_id');
    }

    public function isActive(): bool
    {
        return $this->job_status_id === self::STATUS_PUBLISHED;
    }

    public function isExpired(): bool
    {
        return in_array($this->job_status_id, [self::STATUS_EXPIRED, self::STATUS_TRASHED]);
    }

    public function isDraft(): bool
    {
        return $this->job_status_id === self::STATUS_DRAFT;
    }

    public function scopeActive($query)
    {
        return $query->where('job_status_id', self::STATUS_PUBLISHED);
    }

    public function scopeForSitemap($query, $langId = 1)
    {
        return $query->where('job_status_id', self::STATUS_PUBLISHED)
            ->where('lang_id', $langId)
            ->orderBy('updated_at', 'desc');
    }

    public function scopeForFrontend($query, $langId = 1)
    {
        return $query->where('job_status_id', self::STATUS_PUBLISHED)
            ->where('lang_id', $langId);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', 1);
    }

    public function scopeWithLocalizedNames($query, string $langName)
    {
        $allowed = ['english', 'japanese', 'vietnamese', 'chinese', 'korean'];
        if (! in_array($langName, $allowed, true)) {
            $langName = 'english';
        }

        return $query
            ->join('categories as jc', 'jobs.job_category_id', '=', 'jc.id')
            ->join('prefectures as p', 'jobs.prefecture_id', '=', 'p.id')
            ->join('areas as a', 'jobs.area_id', '=', 'a.id')
            ->join('trans_exp_payments as t', 'jobs.trans_exp_id', '=', 't.id')
            ->join('wage_types as w', 'jobs.wage_type_id', '=', 'w.id')
            ->leftJoin('images as img', 'jobs.img_id', '=', 'img.id')
            ->select([
                'jobs.*',
                "p.{$langName} as prefecture_name",
                "a.{$langName} as area_name",
                'a.postal_code as area_postal_code',
                "t.{$langName} as trans_exp_name",
                "jc.{$langName} as category_name",
                "w.{$langName} as wage_type_name",
                'img.id as images_img_id',
                'img.name as images_img_name',
                'img.ext as images_img_ext',
            ]);
    }

    public function scopeSearch($query, int $langId, string $langName, string $searchQuery = '', int $prefectureId = 0, int $areaId = 0, array $categories = [], array $exceptIds = [])
    {
        $query->withLocalizedNames($langName)
            ->where('jobs.job_status_id', self::STATUS_PUBLISHED)
            ->where('jobs.lang_id', $langId);

        if ($searchQuery !== '') {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('jobs.job_no', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('jobs.title', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('jobs.company_name', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('jobs.description', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('jobs.station', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('jobs.address', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('jobs.requirement', 'LIKE', "%{$searchQuery}%");
            });
        }

        if ($prefectureId > 0) {
            $query->where('jobs.prefecture_id', $prefectureId);
        }
        if ($areaId > 0) {
            $query->where('jobs.area_id', $areaId);
        }
        if (! empty($categories)) {
            $query->whereIn('jobs.job_category_id', $categories);
        }
        if (! empty($exceptIds)) {
            $query->whereNotIn('jobs.id', $exceptIds);
        }

        return $query->orderBy('jobs.id', 'desc');
    }

    public function scopeAllForFrontend($query, string $langName, int $langId)
    {
        return $query->withLocalizedNames($langName)
            ->where('jobs.job_status_id', self::STATUS_PUBLISHED)
            ->where('jobs.lang_id', $langId)
            ->orderBy('jobs.featured', 'desc')
            ->orderBy('jobs.updated_at', 'desc');
    }

    public function scopeForBackend($query, int $userId, int $roleId, int $statusId = 0, int $langId = 0, int $backendUserId = 0, bool $featured = false)
    {
        $query->select([
            'j.*',
            'jc.english as job_category',
            'p.english as prefecture',
            'js.status as job_status',
            'l.english as language',
            \DB::raw("CONCAT(created.first_name, ' ', created.last_name) AS created_by_name"),
            \DB::raw("CONCAT(updated.first_name, ' ', updated.last_name) AS updated_by_name"),
        ])
            ->from('jobs as j')
            ->leftJoin('categories as jc', 'j.job_category_id', '=', 'jc.id')
            ->join('job_status as js', 'j.job_status_id', '=', 'js.id')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->leftJoin('languages as l', 'j.lang_id', '=', 'l.id')
            ->join('users as created', 'j.user_id', '=', 'created.id')
            ->leftJoin('users as updated', 'j.updated_by', '=', 'updated.id')
            ->where('j.job_status_id', '!=', self::STATUS_DRAFT)
            ->orderBy('j.id', 'desc');

        if ($roleId != 1) {
            $query->where('j.user_id', $userId);
        }
        if ($statusId > 0) {
            $query->where('j.job_status_id', $statusId);
        }
        if ($langId > 0) {
            $query->where('j.lang_id', $langId);
        }
        if ($roleId == 1 && $backendUserId > 0) {
            $query->where('j.user_id', $backendUserId);
        }
        if ($featured) {
            $query->where('j.featured', true);
        }

        return $query;
    }

    public function scopeSearchForBackend($query, int $userId, int $roleId, int $statusId, string $search, string $from, string $to)
    {
        $query->select([
            'j.*',
            'jc.english as job_category',
            'p.english as prefecture',
            'js.status as job_status',
            'l.english as language',
            \DB::raw("CONCAT(created.first_name, ' ', created.last_name) AS created_by_name"),
            \DB::raw("CONCAT(updated.first_name, ' ', updated.last_name) AS updated_by_name"),
        ])
            ->from('jobs as j')
            ->join('categories as jc', 'j.job_category_id', '=', 'jc.id')
            ->join('job_status as js', 'j.job_status_id', '=', 'js.id')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->leftJoin('languages as l', 'j.lang_id', '=', 'l.id')
            ->join('users as created', 'j.user_id', '=', 'created.id')
            ->leftJoin('users as updated', 'j.updated_by', '=', 'updated.id')
            ->whereRaw('DATE(j.date) BETWEEN ? AND ?', [$from, $to]);

        if ($roleId != 1) {
            $query->where('j.user_id', $userId);
        }
        if ($statusId > 0) {
            $query->where('j.job_status_id', $statusId);
        }

        $query->where(function ($q) use ($search) {
            $q->where('j.job_no', 'LIKE', "%{$search}%")
                ->orWhere('j.title', 'LIKE', "%{$search}%")
                ->orWhere('j.company_name', 'LIKE', "%{$search}%")
                ->orWhere('j.description', 'LIKE', "%{$search}%")
                ->orWhere('jc.english', 'LIKE', "%{$search}%")
                ->orWhere('p.english', 'LIKE', "%{$search}%")
                ->orWhere('j.apply_link', 'LIKE', "%{$search}%")
                ->orWhere('created.first_name', 'LIKE', "%{$search}%")
                ->orWhere('created.last_name', 'LIKE', "%{$search}%");
        });

        return $query;
    }

    public function scopeForEdit($query, string $jobNo)
    {
        return $query->select([
            'j.*',
            'l.english as language',
            \DB::raw('DATEDIFF(j.delete_at, CURDATE()) AS delete_in_days'),
            'p.english as prefecture',
            'img.id as images_img_id',
            'img.name as images_img_name',
            'img.ext as images_img_ext',
        ])
            ->from('jobs as j')
            ->join('languages as l', 'j.lang_id', '=', 'l.id')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->leftJoin('images as img', 'j.img_id', '=', 'img.id')
            ->where('j.job_no', $jobNo);
    }

    public function scopeForView($query, string $jobNo)
    {
        return $query->select([
            'j.*',
            'l.english as language',
            'jc.english as job_category',
            'p.english as prefecture',
            'a.english as area',
            'wt.english as wage_type',
            'tep.english as trans_exp',
            'img.id as images_img_id',
            'img.name as images_img_name',
            'img.ext as images_img_ext',
        ])
            ->from('jobs as j')
            ->leftJoin('languages as l', 'j.lang_id', '=', 'l.id')
            ->join('categories as jc', 'j.job_category_id', '=', 'jc.id')
            ->join('prefectures as p', 'j.prefecture_id', '=', 'p.id')
            ->leftJoin('areas as a', 'j.area_id', '=', 'a.id')
            ->leftJoin('wage_types as wt', 'j.wage_type_id', '=', 'wt.id')
            ->leftJoin('trans_exp_payments as tep', 'j.trans_exp_id', '=', 'tep.id')
            ->leftJoin('images as img', 'j.img_id', '=', 'img.id')
            ->where('j.job_no', $jobNo);
    }

    public function getSlugAttribute(): string
    {
        $lang = $this->language;
        $langName = $lang ? strtolower($lang->english ?? 'english') : 'english';
        $title = strtolower(trim($this->title ?? ''));
        $title = preg_replace('/[^a-z0-9\s-]/', '', $title);
        $title = preg_replace('/[\s-]+/', '-', $title);

        return "{$langName}-{$title}";
    }

    /**
     * Canonical detail page path for this job (no scheme/host, with slug).
     * Used for Facebook posts, internal links, and canonical URL generation.
     *
     * @return string e.g., "jobs/156344/detail/english-hotel-room-cleaning"
     */
    public function getDetailPathAttribute(): string
    {
        $lang = $this->language;
        $langName = $lang ? strtolower($lang->english ?? 'english') : 'english';
        $slug = \Illuminate\Support\Str::slug(strtolower("{$langName}-{$this->title}"));

        return "jobs/{$this->job_no}/detail/{$slug}";
    }

    /**
     * Parse wage string into Schema.org baseSalary QuantitativeValue format.
     * Returns array for use inside MonetaryAmount->value, or null if unparseable.
     *
     * @param  string|null  $wage  Raw wage string from jobs.wage column
     * @return array|null QuantitativeValue array, or null to omit baseSalary
     *
     * Examples:
     *   "1,270円" -> ['@type' => 'QuantitativeValue', 'value' => 1270, 'unitText' => 'HOUR']
     *   "時給1,500円" -> ['@type' => 'QuantitativeValue', 'value' => 1500, 'unitText' => 'HOUR']
     *   "1,600円～2,000円" -> ['@type' => 'QuantitativeValue', 'minValue' => 1600, 'maxValue' => 2000, 'unitText' => 'HOUR']
     *   "1,450円以上" -> ['@type' => 'QuantitativeValue', 'value' => 1450, 'unitText' => 'HOUR']
     *   "時給1,141円〜" -> ['@type' => 'QuantitativeValue', 'value' => 1141, 'unitText' => 'HOUR']
     *   "時給1,075円（研修中）/ 本採用後 時給1,130円～" -> ['@type' => 'QuantitativeValue', 'value' => 1130, 'unitText' => 'HOUR']
     *   "日給9,000円" -> ['@type' => 'QuantitativeValue', 'value' => 9000, 'unitText' => 'DAY']
     *   "195,000円～216,000円" -> ['@type' => 'QuantitativeValue', 'minValue' => 195000, 'maxValue' => 216000, 'unitText' => 'MONTH']
     *   "時給1,120円～ (高校生 時給1,070円～)" -> null (secondary rates without 本採用 marker)
     */
    public static function parseBaseSalaryLd(?string $wage): ?array
    {
        if (empty(trim($wage ?? ''))) {
            return null;
        }

        // Detect unit from explicit markers (default HOUR for part-time jobs)
        $unitText = 'HOUR';
        $hasExplicitUnit = false;
        if (mb_strpos($wage, '日給') !== false) {
            $unitText = 'DAY';
            $hasExplicitUnit = true;
        } elseif (mb_strpos($wage, '月給') !== false) {
            $unitText = 'MONTH';
            $hasExplicitUnit = true;
        } elseif (mb_strpos($wage, '時給') !== false) {
            $hasExplicitUnit = true;
        }

        // Extract all numbers
        preg_match_all('/\d[\d,]*/', $wage, $matches);
        $numbers = array_map(function ($n) {
            return (int) preg_replace('/[^0-9]/', '', $n);
        }, $matches[0] ?? []);

        if (empty($numbers)) {
            return null;
        }

        // Auto-detect monthly based on MAX amount (only when no explicit unit)
        if (! $hasExplicitUnit && max($numbers) > 50000) {
            $unitText = 'MONTH';
        }

        // Detect range/bound markers
        $hasRangeSep = preg_match('/[～〜~]/u', $wage);
        $hasMin = mb_strpos($wage, '以上') !== false;
        $hasMax = mb_strpos($wage, '以下') !== false;

        // Semantic decision 2026-07-18: open-ended wages ("1,200円～") emit the guaranteed
        // floor as `value` rather than minValue-without-maxValue. The floor IS the base
        // salary (employer guarantees at least this); emitting min-only draws a Google
        // warning on ~1,250 items, and fabricating a ceiling is prohibited. Do not "fix"
        // this back to minValue.

        // Trailing range separator — now emits value (was minValue pre-2026-07-18)
        // Examples: "時給1,141円〜", "1,225円～"
        if (preg_match('/[～〜~]\s*(?:（[^）]*）|\([^)]*\))?\s*$/u', $wage)) {
            if (count($numbers) === 1) {
                return [
                    '@type' => 'QuantitativeValue',
                    'value' => $numbers[0],
                    'unitText' => $unitText,
                ];
            }
        }

        // "以上" with single number — now emits value (was minValue pre-2026-07-18)
        if ($hasMin && count($numbers) === 1) {
            return [
                '@type' => 'QuantitativeValue',
                'value' => $numbers[0],
                'unitText' => $unitText,
            ];
        }

        // "以下" with single number = maxValue only (rare)
        if ($hasMax && count($numbers) === 1) {
            return [
                '@type' => 'QuantitativeValue',
                'maxValue' => $numbers[0],
                'unitText' => $unitText,
            ];
        }

        // Multi-number strings - check if range separator is BETWEEN first two numbers
        if (count($numbers) >= 2) {
            $num1Str = (string) $numbers[0];
            $num2Str = (string) $numbers[1];
            $num1Pattern = number_format($numbers[0]);
            $num2Pattern = number_format($numbers[1]);

            $pos1 = mb_strpos($wage, $num1Pattern);
            if ($pos1 === false) {
                $pos1 = mb_strpos($wage, $num1Str);
            }

            if ($pos1 !== false) {
                $afterFirst = mb_substr($wage, $pos1 + mb_strlen($num1Pattern));
                $pos2InAfterFirst = mb_strpos($afterFirst, $num2Pattern);
                if ($pos2InAfterFirst === false) {
                    $pos2InAfterFirst = mb_strpos($afterFirst, $num2Str);
                }

                if ($pos2InAfterFirst !== false) {
                    $between = mb_substr($afterFirst, 0, $pos2InAfterFirst);
                    $hasRangeBetween = preg_match('/[～〜~]/u', $between);

                    if ($hasRangeBetween) {
                        // Validate: maxValue must be > minValue, else it's secondary rates
                        if ($numbers[1] > $numbers[0]) {
                            return [
                                '@type' => 'QuantitativeValue',
                                'minValue' => $numbers[0],
                                'maxValue' => $numbers[1],
                                'unitText' => $unitText,
                            ];
                        }

                        // Invalid range (max < min) - omit
                        return null;
                    }
                }
            }

            // No range separator between first two numbers = training/secondary rates
            // Example: "時給1,075円（研修中）/ 本採用後 時給1,130円～"
            // Prefer rate near 本採用, otherwise omit
            if (mb_strpos($wage, '本採用') !== false) {
                if (preg_match('/本採用[^0-9]*?([\d,]+)/u', $wage, $m)) {
                    $afterHireValue = (int) preg_replace('/[^0-9]/', '', $m[1]);
                    if ($afterHireValue > 0) {
                        // Check if this number is followed by trailing range separator
                        $afterHireFullMatch = $m[0];
                        $afterHirePos = mb_strpos($wage, $afterHireFullMatch);
                        $afterHireRest = mb_substr($wage, $afterHirePos);

                        // both paths now emit value per 2026-07-18 decision; conditional retained for minimal diff
                        if (preg_match('/[～〜~]\s*$/u', $afterHireRest)) {
                            return [
                                '@type' => 'QuantitativeValue',
                                'value' => $afterHireValue,
                                'unitText' => $unitText,
                            ];
                        }

                        return [
                            '@type' => 'QuantitativeValue',
                            'value' => $afterHireValue,
                            'unitText' => $unitText,
                        ];
                    }
                }
            }

            // Can't determine which rate to use - omit baseSalary
            return null;
        }

        // Single number, no special markers
        if (count($numbers) === 1) {
            return [
                '@type' => 'QuantitativeValue',
                'value' => $numbers[0],
                'unitText' => $unitText,
            ];
        }

        return null;
    }
}
