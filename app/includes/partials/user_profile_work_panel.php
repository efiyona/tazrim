<?php
/**
 * פנל ניהול עבודות — דורש $work_jobs (מערך: שורת job + 'types' => []).
 */
$work_jobs = $work_jobs ?? [];
$icP = static function (string $p): string {
    $m = ['morning' => 'fa-sun', 'evening' => 'fa-cloud-sun', 'mid' => 'fa-clock', 'night' => 'fa-moon'];
    $p = $p === '' ? 'morning' : $p;
    return $m[$p] ?? 'fa-sun';
};
?>
<div class="manage-categories-toolbar user-profile-work-toolbar">
    <h2 class="section-subtitle" style="margin:0">עבודות וסוגי משמרת</h2>
    <button type="button" class="btn-primary" style="width:max-content;margin:0;padding:8px 20px;font-size:0.95rem" onclick="upWorkOpenAddJob()">
        הוספת עבודה <i class="fa-solid fa-plus" aria-hidden="true"></i>
    </button>
</div>
<div id="user-profile-work-list" class="user-profile-work-list">
    <?php if (count($work_jobs) === 0): ?>
        <div class="empty-state text-center" style="padding:32px 20px;background:var(--white);border-radius:15px">
            <i class="fa-solid fa-briefcase" style="font-size:2.5rem;color:var(--gray);margin-bottom:10px;display:block" aria-hidden="true"></i>
            <p style="color:var(--text-light);margin:0">אין עבודות. הוסיפו מקור הכנסה לסידור המשמרות.</p>
        </div>
    <?php else: ?>
        <?php foreach ($work_jobs as $j):
            $jid = (int) $j['id'];
            $col = $j['color'] ?: '#5B8DEF';
            $ptitle = (string) $j['title'];
            $pd = (int) ($j['payday_day_of_month'] ?? 10);
        ?>
        <div class="user-profile-work-job">
            <div class="user-profile-work-job__row">
                <div class="user-profile-work-job__main" role="button" tabindex="0" onclick="upWorkOpenEditJob(<?php echo $jid; ?>,<?php echo json_encode($ptitle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,<?php echo json_encode($col, JSON_UNESCAPED_UNICODE); ?>,<?php echo (int) $pd; ?>)">
                    <span class="user-profile-work-job__sw" style="background:<?php echo htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?>"></span>
                    <div>
                        <div class="user-profile-work-job__title"><?php echo htmlspecialchars($ptitle, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="user-profile-work-job__meta">יום שכר: <?php echo (int) $pd; ?> לחודש</div>
                    </div>
                </div>
                <button type="button" class="user-profile-work-iconbtn" title="מחיקת עבודה" onclick="event.stopPropagation();upWorkDeleteJob(<?php echo $jid; ?>)">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
            </div>
            <div class="user-profile-work-types">
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
                ?>
                <?php
                $typeEditOnclick = 'upWorkOpenEditType(' . $ti . ',' . $jid . ',' . json_encode($tn, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . ',' . json_encode($ip, JSON_UNESCAPED_UNICODE) . ',' . json_encode($tstart, JSON_UNESCAPED_UNICODE) . ',' . json_encode($tend, JSON_UNESCAPED_UNICODE) . ',' . $tso . ')';
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
