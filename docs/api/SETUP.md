# Auto-updating API docs (Scramble): setup

1. Install (once):
   ```bash
   composer require dedoc/scramble
   ```
   `AppServiceProvider` is already prepared (bearer auth, title, docs gate), so nothing else is needed.
2. Open `http://localhost:8000/docs/api` for the interactive docs and `/docs/api.json` for the OpenAPI spec.
3. Docs are visible in `local` and `staging` only. Change the list in `AppServiceProvider::boot()` (`viewApiDocs` gate) to expose them elsewhere.
4. To hand the frontend developer a file: `php artisan scramble:export` (writes `api.json` in the project root; use `--path=` to change it).
5. Keep it fresh in CI/deploy: run `php artisan scramble:export` as a deploy step and publish the file (or just point the frontend at `/docs/api.json` on staging).

## Getting richer docs
Scramble infers request bodies from your Form Requests, so those are already documented. Responses are thinner because most controllers return `response()->json([...])` or paginators of Eloquent models. To improve them over time:
- Return typed API Resources (`return new ClientResource($client);`) or add `@response` / `@return` docblocks.
- Mark public endpoints (login, OTP, awareness, webhooks) with `@unauthenticated` in the controller docblock so they don't show a lock.
- Closure routes (`/areas`, `/areas/debug`, `/user`) are documented poorly. Move them into controllers.
