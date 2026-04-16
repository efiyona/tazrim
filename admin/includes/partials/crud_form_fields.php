<?php foreach (($config['fields'] ?? []) as $name => $fieldDef):
    $type = $fieldDef['type'] ?? 'text';
    $label = $fieldDef['label'] ?? $name;
    $val = tazrim_admin_field_value($fieldDef, $row ?? [], $name);
?>
    <div class="admin-field mb-4 <?php echo $type === 'checkbox' ? 'admin-field--boolean' : ''; ?>">
        <?php if ($type === 'checkbox'): ?>
            <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" class="admin-boolean-select">
                <option value="1" <?php echo $val === '1' ? 'selected' : ''; ?>>כן</option>
                <option value="0" <?php echo $val !== '1' ? 'selected' : ''; ?>>לא</option>
            </select>
        <?php elseif ($type === 'enum'): ?>
            <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                <?php
                $opts = isset($fieldDef['enum_options']) && is_array($fieldDef['enum_options']) ? $fieldDef['enum_options'] : [];
                foreach ($opts as $ov => $olabel):
                ?>
                    <option value="<?php echo htmlspecialchars((string) $ov, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) $val === (string) $ov ? 'selected' : ''; ?>><?php echo htmlspecialchars($olabel, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'password_new'): ?>
            <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="password" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="" autocomplete="new-password" placeholder="<?php echo !empty($editId) ? 'השאר ריק אם אין שינוי' : ''; ?>">
        <?php elseif ($type === 'fk_lookup'):
            $fkCfg = $fieldDef['fk'] ?? [];
            $fkOpt = !empty($fkCfg['optional']);
            $sid = $val !== '' ? (int) $val : 0;
            $initialFkLabel = ($sid > 0 && $fkCfg) ? tazrim_admin_fk_lookup_resolve_label($fkCfg, $sid) : '';
            ?>
            <div class="admin-fk-lookup"
                data-entity="<?php echo htmlspecialchars($tableKey, ENT_QUOTES, 'UTF-8'); ?>"
                data-field="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                data-optional="<?php echo $fkOpt ? '1' : '0'; ?>"
            >
                <label for="fk_search_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="hidden" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>" class="admin-fk-value">
                <div class="admin-fk-lookup__controls">
                    <input type="search" id="fk_search_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" class="admin-fk-search" placeholder="חיפוש ובחירה מהרשימה" value="<?php echo htmlspecialchars($initialFkLabel, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" data-locked-label="<?php echo htmlspecialchars($initialFkLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($fkOpt): ?>
                        <button type="button" class="admin-fk-clear">ללא</button>
                    <?php endif; ?>
                </div>
                <ul class="admin-fk-results hidden" role="listbox"></ul>
            </div>
        <?php else: ?>
            <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
            <?php if ($type === 'textarea'): ?>
                <?php $taRows = isset($fieldDef['rows']) ? max(4, min(50, (int) $fieldDef['rows'])) : 12; ?>
                <textarea id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" rows="<?php echo (int) $taRows; ?>"><?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <?php elseif ($type === 'number' || $type === 'balance'): ?>
                <input type="number" step="<?php echo $type === 'balance' ? 'any' : '1'; ?>" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
            <?php elseif ($type === 'datetime'): ?>
                <input type="datetime-local" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
            <?php else: ?>
                <input type="text" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($sqlTable === 'homes' && $name === 'join_code') ? 'maxlength="4"' : ''; ?>>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
