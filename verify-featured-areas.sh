#!/bin/bash
# Verify all featured area slugs resolve and have non-zero job counts

DB_USER="u6fiwi0ylbrr1"
DB_PASS="k92;)o<*5#gs"
DB_NAME="dbxrpxuz9wqtbp"

echo "=== HAND-CASH AREAS ==="
echo ""

HAND_CASH_SLUGS=(
    "kita-ku-osaka"
    "chiyoda-ku"
    "chuo-ku-osaka"
    "minato-ku"
    "shinjuku-ward"
    "hakata-ku-fukuoka-city"
    "chuo-ku-fukuoka"
    "shibuya-ward"
    "taito"
    "nishi-ku-osaka"
    "setagaya"
    "kyoto-shimogyo"
)

for slug in "${HAND_CASH_SLUGS[@]}"; do
    result=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "
        SELECT
            a.id,
            a.english,
            COUNT(j.id) as job_count
        FROM areas a
        LEFT JOIN jobs j ON j.area_id = a.id
            AND j.job_status_id = 3
            AND (j.title LIKE '%hand cash%' OR j.description LIKE '%hand cash%')
        WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(a.english, ' ', '-'), '.', '-'), '_', '-'), '/', '-')) = '$slug'
        GROUP BY a.id, a.english;
    " 2>/dev/null)

    if [ -z "$result" ]; then
        echo "SLUG MISS  $slug"
    else
        area_id=$(echo "$result" | awk '{print $1}')
        area_name=$(echo "$result" | awk '{$1=""; print $0}' | awk '{$NF=""; print $0}' | sed 's/^[[:space:]]*//')
        job_count=$(echo "$result" | awk '{print $NF}')

        if [ "$job_count" -eq 0 ]; then
            echo "ZERO JOBS  $slug  ($area_name)"
        else
            echo "OK  $slug  jobs=$job_count  ($area_name)"
        fi
    fi
done

echo ""
echo "=== DAILY PAYMENT AREAS ==="
echo ""

DAILY_PAYMENT_SLUGS=(
    "minato-ku"
    "higashi-osaka-city"
    "shinagawa"
    "aoba-ku-sendai"
    "koto"
    "funabashi"
    "chuo-ku-osaka"
    "taito"
    "ota-ku"
    "matsudo"
    "utsunomiya"
    "nakano"
    "setagaya"
    "ichikawa"
    "ibaraki"
    "misato-city"
    "shinjuku-ward"
    "shibuya-ward"
    "edogawa"
    "kawasaki-ku"
)

for slug in "${DAILY_PAYMENT_SLUGS[@]}"; do
    result=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "
        SELECT
            a.id,
            a.english,
            COUNT(j.id) as job_count
        FROM areas a
        LEFT JOIN jobs j ON j.area_id = a.id
            AND j.job_status_id = 3
            AND (j.title LIKE '%daily payment%' OR j.description LIKE '%daily payment%'
                 OR j.title LIKE '%daily pay%' OR j.description LIKE '%daily pay%')
        WHERE LOWER(REPLACE(REPLACE(REPLACE(REPLACE(a.english, ' ', '-'), '.', '-'), '_', '-'), '/', '-')) = '$slug'
        GROUP BY a.id, a.english;
    " 2>/dev/null)

    if [ -z "$result" ]; then
        echo "SLUG MISS  $slug"
    else
        area_id=$(echo "$result" | awk '{print $1}')
        area_name=$(echo "$result" | awk '{$1=""; print $0}' | awk '{$NF=""; print $0}' | sed 's/^[[:space:]]*//')
        job_count=$(echo "$result" | awk '{print $NF}')

        if [ "$job_count" -eq 0 ]; then
            echo "ZERO JOBS  $slug  ($area_name)"
        else
            echo "OK  $slug  jobs=$job_count  ($area_name)"
        fi
    fi
done

echo ""
echo "=== SUMMARY ==="
echo "All lines must read 'OK' with non-zero counts for safe deployment."
echo "SLUG MISS = sitemap will 404, pull from config"
echo "ZERO JOBS = will trip noindex guard, pull from config"
