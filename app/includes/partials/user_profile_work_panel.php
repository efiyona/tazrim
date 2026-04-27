<?php
/**
 * פנל ניהול עבודות — דורש $work_jobs (מערך: שורת job + 'types' => []).
 * מבנה ויזואלי מיושר ל־manage_home_shopping_stores_panel (כרטיס + שורות transaction-item).
 */
$work_jobs = $work_jobs ?? [];
$icP = static function (string $p): string {
    $m = ['morning' => 'fa-sun', 'evening' => 'fa-cloud-sun', 'mid' => 'fa-clock', 'night' => 'fa-moon'];
    $p = $p === '' ? 'morning' : $p;
    return $m[$p] ?? 'fa-sun';
};
?>
<div class="manage-categories-toolbar">
    <h2 class="section-subtitle" style="margin:0">עבודות וסוגי משמרת</h2>
    <button type="button" class="btn-primary" style="width:max-content;margin:0;padding:8px 20px;font-size:0.95rem;box-shadow:0 4px 10px rgba(35,114,39,0.2);" onclick="upWorkOpenAddJob()">
        הוספת עבודה <i class="fa-solid fa-plus" aria-hidden="true"></i>
    </button>
</div>
<div id="user-profile-work-list" class="user-profile-work-list">
    <?php if (count($work_jobs) === 0): ?>
        <div class="empty-state text-center" style="padding:40px;background:var(--white);border-radius:15px;">
            <i class="fa-solid fa-briefcase" style="font-size:3rem;color:var(--gray);margin-bottom:15px;display:block" aria-hidden="true"></i>
            <p style="color:var(--text-light);margin:0">אין עבודות. הוסיפו מקור הכנסה לסידור המשמרות.</p>
        </div>
    <?php else:
        $workJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
        foreach ($work_jobs as $j):
            $jid = (int) $j['id'];
            $col = $j['color'] ?: '#5B8DEF';
            $ptitle = (string) $j['title'];
            $pd = (int) ($j['payday_day_of_month'] ?? 10);
            $jobEditOnclick = 'upWorkOpenEditJob(' . $jid . ',' . json_encode($ptitle, $workJsonFlags) . ',' . json_encode($col, $workJsonFlags) . ',' . $pd . ')';
        ?>
        <div class="user-profile-work-job-stack">
            <div class="transaction-item expense user-profile-work-job-head" role="button" tabindex="0" onclick="<?php echo htmlspecialchars($jobEditOnclick, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="transaction-info">
                    <div class="cat-icon-wrapper" style="background:<?php echo htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?>;">
                        <i class="fa-solid fa-briefcase" style="font-size:1.1rem;color:#fff;" aria-hidden="true"></i>
                    </div>
                    <div class="details">
                        <span class="desc"><?php echo htmlspecialchars($ptitle, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="date">יום שכר: <?php echo (int) $pd; ?> לחודש</span>
                    </div>
                </div>
                <div class="transaction-actions">
                    <div class="transaction-row-actions">
                        <div class="transaction-action-pill" title="עריכת עבודה" aria-hidden="true">
                            <i class="fa-solid fa-pen" style="font-size:0.9rem;"></i>
                        </div>
                        <button type="button" class="transaction-action-pill transaction-action-pill--danger" title="מחיקת עבודה" onclick="event.stopPropagation();upWorkDeleteJob(<?php echo $jid; ?>)">
                            <i class="fa-solid fa-trash-can" style="font-size:1rem;" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="user-profile-work-types-below">
                <div class="user-profile-work-types__head">סוגי משמרת</div>
                <?php
                $types = $j['types'] ?? [];
                if (count($types) === 0) { ?>
                    <p class="user-profile-work-types__empty">אין סוגי משמרת</p>
                <?php } else { foreach ($types as $t):
                    $ti = (int) $t['id'];
                    $tn = (string) $t['name'];
                    $ip = (string) ($t['icon_preset'] ?? 'morning');
                    $tstart = $t['default_start_time'] ? substr($t['default_start_time'], 0, 5) : '';
                    $tend = $t['default_end_time'] ? substr($t['default_end_time'], 0, 5) : '';
                    $tso = (int) ($t['sort_order'] ?? 0);
                    $fa = $icP($ip);
                    $typeEditOnclick = 'upWorkOpenEditType(' . $ti . ',' . $jid . ',' . json_encode($tn, $workJsonFlags) . ',' . json_encode($ip, $workJsonFlags) . ',' . json_encode($tstart, $workJsonFlags) . ',' . json_encode($tend, $workJsonFlags) . ',' . $tso . ')';
                ?>
                <div class="user-profile-work-type">
                    <div class="user-profile-work-type__main" role="button" tabindex="0" onclick="<?php echo htmlspecialchars($typeEditOnclick, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="user-profile-work-type__ic" aria-hidden="true"><i class="fa-solid <?php echo htmlspecialchars($fa, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                        <div class="user-profile-work-type__body">
                            <div class="user-profile-work-type__name"><?php echo htmlspecialchars($tn, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ($tstart !== '' || $tend !== ''): ?><div class="user-profile-work-type__t"><?php echo htmlspecialchars($tstart, ENT_QUOTES, 'UTF-8'); ?><?php echo ($tstart !== '' && $tend !== '') ? ' – ' : ''; ?><?php echo htmlspecialchars($tend, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="user-profile-work-type__edit" title="עריכה" aria-label="עריכת סוג משמרת" onclick="<?php echo htmlspecialchars($typeEditOnclick, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    </button>
                </div>
                <?php endforeach; ?>
                <?php } ?>
                <button type="button" class="btn-primary user-profile-work-addtype" onclick="upWorkOpenAddType(<?php echo $jid; ?>)">+ סוג משמרת</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
