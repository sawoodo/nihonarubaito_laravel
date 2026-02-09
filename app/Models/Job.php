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
        if (!in_array($langName, $allowed, true)) {
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
        if (!empty($categories)) {
            $query->whereIn('jobs.job_category_id', $categories);
        }
        if (!empty($exceptIds)) {
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
        ->whereRaw("DATE(j.date) BETWEEN ? AND ?", [$from, $to]);

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
}
