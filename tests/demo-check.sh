#!/usr/bin/env bash
# CI: starts the demo image twice against MySQL with the stand-in API, checks the demo accounts
# (created once, never duplicated, passwords never logged) and translates the sample products.
# Needs: docker image supertext-atropim-demo, MySQL at $MYSQL_URL (mysql client on the host, or
# MYSQL_CMD), the stand-in on 127.0.0.1:8765.
set -euo pipefail
# No pipes into grep -q: with pipefail they can fail on SIGPIPE. Capture the output, then grep a here-string.
PORT=8080
DB=atropim_ci
export DEMO_ADMIN_EMAIL=ci-admin@example.com DEMO_ADMIN_PASSWORD="Ci-$(openssl rand -hex 8)-1"
export DEMO_EDITOR_EMAIL=ci-editor@example.com DEMO_EDITOR_PASSWORD="Ci-$(openssl rand -hex 8)-2"
MYSQL_CMD="${MYSQL_CMD:-mysql -h127.0.0.1 -uroot -proot}"
sql() { $MYSQL_CMD --default-character-set=utf8mb4 "$DB" -N -s -e "$1" 2>/dev/null; }
console() { docker exec demo demo-console "$@"; }

start() {
	docker rm -f demo >/dev/null 2>&1 || true
	docker run -d --name demo --network host -e PORT=$PORT -e MYSQL_URL="$MYSQL_URL" -e ATROPIM_DB_NAME=$DB \
		-e DEMO_SITE_URL=http://127.0.0.1:$PORT -e DEMO_ADMIN_EMAIL -e DEMO_ADMIN_PASSWORD -e DEMO_EDITOR_EMAIL -e DEMO_EDITOR_PASSWORD \
		-e SUPERTEXT_API_KEY=anything -e SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/ -v atropim-ci-data:/data supertext-atropim-demo >/dev/null
	for _ in $(seq 200); do curl -sf -o /dev/null http://127.0.0.1:$PORT/ && break; sleep 3; done
	curl -sf -o /dev/null http://127.0.0.1:$PORT/
	docker logs demo 2>&1 | grep '\[demo\]' || true
}

docker volume rm -f atropim-ci-data >/dev/null 2>&1 || true
start
start   # second start: nothing duplicated or changed
logs=$(docker logs demo 2>&1)
grep -qF 'DEMO_EDITOR: account exists, left unchanged' <<< "$logs"
if grep -qF -e "$DEMO_ADMIN_PASSWORD" -e "$DEMO_EDITOR_PASSWORD" <<< "$logs"; then echo "A password appeared in the log"; exit 1; fi

test "$(sql "select group_concat(user_name order by user_name) from user where user_name like 'ci-%' and deleted = 0")" = "ci-admin@example.com,ci-editor@example.com"
test "$(sql "select is_admin from user where user_name = 'ci-admin@example.com'")" = 1
test "$(sql "select r.name from user u join role_user ru on ru.user_id = u.id join role r on r.id = ru.role_id where u.user_name = 'ci-editor@example.com'")" = "Product editor (Supertext demo)"
test "$(sql "select count(*) from product where number in ('praline-box-16', 'dark-chocolate-72') and deleted = 0")" = 2
test "$(sql "select count(*) from action where type = 'supertextTranslate' and deleted = 0")" = 1

console supertext check
praline=$(sql "select id from product where number = 'praline-box-16'")
out=$(console supertext translate Product "$praline")
echo "$out"
grep -qF 'de_CH, fr_CH, it_CH: translated (4 fields)' <<< "$out"
test "$(sql "select name_de_ch from product where id = '$praline'")" = "Handgemachte Pralinenschachtel, 16 Stück"
grep -qF '<strong>à la main</strong>' <<< "$(sql "select long_description_fr_ch from product where id = '$praline'")"
test "$(sql "select text_value_it_ch from product_attribute_value where product_id = '$praline'")" = "Nocciola, caramello e un pizzico di sale marino."

# A second run keeps the languages that now have their own text.
grep -qF 'de_CH, fr_CH, it_CH: kept, already translated' <<< "$(console supertext translate Product "$praline")"

# The action, as the editor would run it from the product list (mass action, REST API).
token=$(curl -sf -H "Authorization-Token: $(printf '%s:%s' "$DEMO_EDITOR_EMAIL" "$DEMO_EDITOR_PASSWORD" | base64 -w0)" http://127.0.0.1:$PORT/api/userSession | python3 -c 'import json,sys; print(json.load(sys.stdin)["user"]["token"])')
action=$(sql "select id from action where type = 'supertextTranslate'")
result=$(curl -sf -H "Authorization-Token: $(printf '%s:%s' "$DEMO_EDITOR_EMAIL" "$token" | base64 -w0)" -H 'Content-Type: application/json' \
	-X POST "http://127.0.0.1:$PORT/api/Action/$action/supertextTranslate" -d '{"where":[{"type":"equals","attribute":"number","value":"dark-chocolate-72"}],"massAction":true}')
echo "$result"
grep -qF 'translated (4 fields)' <<< "$result"
test "$(sql "select name_fr_ch from product where number = 'dark-chocolate-72'")" = "Tablette de chocolat noir, 72 % de cacao"
echo "Demo check passed"
