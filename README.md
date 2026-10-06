# PriceBoard

Tablo e moghayese e gheymat e tala/noghre/mes va platform e foroosh e API. Laravel13/PHP8.5, PostgreSQL18, Redis8, InfluxDB2.7, Octane/Swoole, Alpine3/Tailwind4/Vite8.

## Ejra e local

```sh
cp .env.example .env
# credential ha va APP_KEY ra dar mohit e khodet tanzim kon.
docker compose -f docker-compose.local.yml up -d --build
npm install
npm run build
docker compose -f docker-compose.local.yml exec octane php artisan migrate --force
docker compose -f docker-compose.local.yml exec octane php artisan db:seed
docker compose -f docker-compose.local.yml exec octane php artisan octane:reload
```

App ru `http://localhost:8300`, dashboard ru `/dashboard`, hesab e customer ru `/account`. Login mobile/OTP ast. Seeder provider ha ro inactive misaze ta admin marja e motabar va source haye lazem ro entekhab kone. `.env` ra commit nakon; Zohal/Zibal/Kavenegar faghat ba credential/template e khodet faal mishan. `BYPASS_OTP` faghat baraye local ast.

Redis faghat yek service ru56381: DB0 session/queue/OTP, DB1 cache, DB2 prices; noeviction+AOF. PostgreSQL55434, Influx8386, Vite5373. Hame mabalegh rial-and; ons/quote e jahani USD mimune.

## Bakhsh ha

- Providers+salamat e zende; priorities+manual-only+backup+Excel.
- Users haghighi/hoghoghi, estelam e Zohal, tickets ba madarek e private.
- RBAC role/permission, audit, SEO editor, media library.
- Mali e kol: wallet ledger, journal do-tarafe, trial balance, reversal/correction, settlement, invoice.
- Customer: site/credentials, taeed e site, plans/subscription/quota, wallet/topup, usage va tickets.

## Test

```sh
docker compose -f docker-compose.local.yml exec -T octane php artisan test --compact
npx playwright test
vendor/bin/pint --dirty --format agent
```

PHP test ha SQLite e in-memory va Redis13/14/15 estefade mikonan. Test e PG e mali faghat ru database e disposable ba name e `priceboard_verify_*` va env e `PB_TEST_PG_DATABASE` ejra mishe; default DB ra reset nakon. Browser suite ba stack e local va assets e build-shode ejra mishe.

## Mostanadat

- [Task ha va vaziyat](docs/TASKS.md)
- [Plan va tasmim ha](docs/PLAN.md)
- [Meamari, mali/RBAC/KYC va migration runbook](docs/architecture.md)
- [Dashboard kit va token ha](docs/dashboard-design.md)
- [Workflow e repository](CLAUDE.md)

Baseline e migration ha ba darkhast e sahebe proje consolidate shode. Database e ghadimi bayad aval upgrade e additive e pre-baseline ra daryaft karde bashe; `migrate:fresh` ru data e mojood mamnu ast.
