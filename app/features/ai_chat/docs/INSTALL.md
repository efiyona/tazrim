# AI Chat Feature Install

## 1) Copy Feature Folder

Copy `app/features/ai_chat/` into the target project as-is.

## 2) Required Host Dependencies

The host project must provide:

- `ROOT_PATH` and `BASE_URL` constants (via `path.php` style bootstrap).
- Active PHP session with `$_SESSION['id']` (logged-in user id).
- Active mysqli connection in `$conn` (via `connect.php` / `db.php`).
- `GEMINI_API_KEY` constant in environment/secrets.

## 3) Header Integration

In the shared header include:

1. `require_once ROOT_PATH . '/app/features/ai_chat/bootstrap.php';`
2. Render launcher inside action icons: `ai_chat_render_launcher_button();`
3. Render modal + assets once near end of body:
   - `ai_chat_render_modal();`
   - `ai_chat_render_assets();`

## 4) Database

Run migration:

- `docs/database/migrations/20260415_ai_chats_user_scoped.sql`

## 5) Notes

- Chats are stored per `user_id` (not per home).
- Scope (`calendar_month` / `specific_month` / `all`) is saved at chat level.
- API routes are under `app/features/ai_chat/api/`.

## 6) Product knowledge (help for the AI)

- Detailed, app-specific help for prompts lives in [`product_knowledge.md`](product_knowledge.md) (Hebrew).
- Update `version` / `last_updated` when you change the product or navigation.
