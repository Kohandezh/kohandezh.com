#!/bin/zsh
# One-shot: import the archived kohandezh.com blog (posts + media + categories)
# into the local Docker WordPress, fully offline.
# Source of data: _archive/kohandez_dbase.sql (full dump, Dec 2024)
#                 _archive/kohandezh.com/wp-content/uploads (554MB media)
set -e
cd "$(dirname "$0")"
ROOT=..

# OBSOLETE as of 2026-08-09. This script read the live site's December-2024
# database dump and its uploads tree from _archive/. Both were already gone
# before _archive/ itself was deleted, so there is nothing left to import —
# the blog now lives in the repo as static pages under blog/ and is carried
# into WordPress by _tooling/wp-theme/kohandezhcv-blog-import*.wxr.xml.
# Kept as a record of how the original import was done.
if [ ! -f "$ROOT/_archive/kohandez_dbase.sql" ]; then
  echo "import-live-blog.sh: source data no longer exists (_archive/ was removed)." >&2
  echo "The blog is now imported from _tooling/wp-theme/kohandezhcv-blog-import-fixed.wxr.xml." >&2
  exit 1
fi

echo "== 0. stack up =="
docker compose up -d db wordpress
sleep 8

echo "== 1. load dump into temp DB 'oldwp' =="
docker compose exec -T db mariadb -uroot -prootpass -e "DROP DATABASE IF EXISTS oldwp; CREATE DATABASE oldwp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker compose exec -T db mariadb -uroot -prootpass --force oldwp < "$ROOT/_archive/kohandez_dbase.sql"
docker compose exec -T db mariadb -uroot -prootpass -e "SELECT COUNT(*) AS old_published_posts FROM oldwp.wp_posts WHERE post_type='post' AND post_status='publish';"

echo "== 2. remove the 4 manually-created duplicate posts (14-17) =="
docker compose --profile cli run --rm cli post delete 14 15 16 17 --force || true

echo "== 3. migrate posts + attachments + meta + categories/tags =="
docker compose exec -T db mariadb -uroot -prootpass <<'SQL'
INSERT IGNORE INTO wordpress.wp_terms SELECT * FROM oldwp.wp_terms;
INSERT IGNORE INTO wordpress.wp_term_taxonomy SELECT * FROM oldwp.wp_term_taxonomy WHERE taxonomy IN ('category','post_tag');
INSERT IGNORE INTO wordpress.wp_posts SELECT * FROM oldwp.wp_posts WHERE (post_type='post' AND post_status='publish') OR (post_type='attachment' AND post_status='inherit');
INSERT IGNORE INTO wordpress.wp_postmeta SELECT * FROM oldwp.wp_postmeta WHERE post_id IN (SELECT ID FROM oldwp.wp_posts WHERE (post_type='post' AND post_status='publish') OR (post_type='attachment' AND post_status='inherit'));
INSERT IGNORE INTO wordpress.wp_term_relationships SELECT * FROM oldwp.wp_term_relationships WHERE object_id IN (SELECT ID FROM oldwp.wp_posts WHERE post_type='post' AND post_status='publish');
SQL

echo "== 4. copy media library (554MB) =="
docker cp "$ROOT/_archive/kohandezh.com/wp-content/uploads" kohandezh_wp:/var/www/html/wp-content/
docker compose exec -u root wordpress chown -R www-data:www-data /var/www/html/wp-content/uploads

echo "== 5. rewrite live URLs -> local (offline) =="
docker compose --profile cli run --rm cli search-replace 'https://kohandezh.com/wp-content/uploads' 'http://localhost:8888/wp-content/uploads' wp_posts wp_postmeta --precise
docker compose --profile cli run --rm cli search-replace 'http://kohandezh.com/wp-content/uploads' 'http://localhost:8888/wp-content/uploads' wp_posts wp_postmeta --precise
# internal post-to-post links stay on the same host too
docker compose --profile cli run --rm cli search-replace 'https://kohandezh.com/' 'http://localhost:8888/' wp_posts --precise

echo "== 6. recount terms, flush =="
docker compose --profile cli run --rm cli term recount category post_tag
docker compose --profile cli run --rm cli cache flush || true
docker compose --profile cli run --rm cli rewrite flush

echo "== 7. verify =="
docker compose --profile cli run --rm cli post list --post_type=post --post_status=publish --format=count
curl -s -o /dev/null -w "blog index: %{http_code}\n" http://localhost:8888/blog/
SLUG=$(docker compose --profile cli run --rm cli post list --post_type=post --post_status=publish --field=post_name --posts_per_page=1 | tr -d '\r')
curl -s -o /dev/null -w "sample post /$SLUG/: %{http_code}\n" "http://localhost:8888/$SLUG/"
echo "== drop temp DB =="
docker compose exec -T db mariadb -uroot -prootpass -e "DROP DATABASE oldwp;"
echo "== DONE =="
