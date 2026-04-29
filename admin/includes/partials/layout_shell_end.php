            </div>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars(tazrim_admin_asset_href('admin/assets/js/admin_shell.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
include ROOT_PATH . '/assets/includes/gemini_key_modal.php';
require_once ROOT_PATH . '/admin/features/ai_chat/bootstrap.php';
admin_ai_chat_render_launcher_button();
admin_ai_chat_render_lazy_loader();
?>
