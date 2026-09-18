<?php
/**
 * Holiday Add/Edit — supports department_id scope from department modules
 */
$masterKey = 'holidays';
require_once __DIR__ . '/../_core/bootstrap.php';
require_once __DIR__ . '/../../includes/employee_helper.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$scopeDeptId = (int) ($_GET['department_id'] ?? 0);
$row = null;
if ($id > 0) {
    $row = getMasterRow($master['table'], $id);
    if (!$row || (int) ($row['status'] ?? 1) !== 1) {
        header('Location: ' . app_url('masters/holidays/index.php' . ($scopeDeptId > 0 ? '?department_id=' . $scopeDeptId : '')));
        exit;
    }
    if ($scopeDeptId <= 0 && !empty($row['department_id'])) {
        $scopeDeptId = (int) $row['department_id'];
    }
}

$scopeDept = $scopeDeptId > 0 ? getDepartmentById($scopeDeptId) : null;
if ($scopeDeptId > 0 && !$scopeDept) {
    $scopeDeptId = 0;
}

// When opened from a department, default/lock department field
if ($scopeDeptId > 0) {
    foreach ($master['fields'] as $fi => $field) {
        if (($field['name'] ?? '') === 'department_id') {
            $master['fields'][$fi]['default'] = (string) $scopeDeptId;
            $master['fields'][$fi]['options'] = [
                '0' => 'All Departments',
                (string) $scopeDeptId => (string) $scopeDept['department_name'],
            ];
            // Prefer this department when adding
            if (!$row) {
                $master['fields'][$fi]['default'] = (string) $scopeDeptId;
            }
        }
    }
}

$deptQs = $scopeDeptId > 0 ? ('?department_id=' . $scopeDeptId) : '';
$masterListUrl = app_url('masters/holidays/index.php' . $deptQs);
$masterSaveUrl = app_url('masters/holidays/save.php');

$pageTitle = ($row ? 'Edit ' : 'Add ') . $master['singular'];
$useSidebar = true;
if ($scopeDeptId > 0) {
    $sidebarMode = 'department';
    $sidebarDeptId = $scopeDeptId;
} else {
    $sidebarMode = 'masters';
}
$sidebarActive = $master['key'];

require_once __DIR__ . '/../../includes/header.php';

function holidayFieldValue($row, $field)
{
    $name = $field['name'];
    if ($row && array_key_exists($name, $row) && $row[$name] !== null) {
        return $row[$name];
    }
    return $field['default'] ?? '';
}
?>

<main class="dashboard-main">
    <div class="page-toolbar">
        <a href="<?php echo htmlspecialchars($masterListUrl); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to <?php echo htmlspecialchars($master['title']); ?>
        </a>
    </div>

    <div class="form-page-card master-form-card">
        <div class="form-page-header">
            <div class="master-list-title">
                <div class="master-list-icon" style="background: <?php echo htmlspecialchars($master['color']); ?>;">
                    <i class="fa-solid <?php echo htmlspecialchars($master['icon']); ?>"></i>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>
                        <?php if ($scopeDept): ?>
                            Department scope: <strong><?php echo htmlspecialchars($scopeDept['department_name']); ?></strong>
                        <?php else: ?>
                            Choose All Departments or one department
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($masterSaveUrl); ?>" class="employee-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <?php if ($scopeDeptId > 0): ?>
                <input type="hidden" name="return_department_id" value="<?php echo (int) $scopeDeptId; ?>">
            <?php endif; ?>

            <div class="form-section">
                <div class="form-grid form-grid-3">
                    <?php foreach ($master['fields'] as $field): ?>
                        <?php
                        $name = $field['name'];
                        $type = $field['type'];
                        $span = (int) ($field['span'] ?? 1);
                        $spanClass = $span >= 3 ? 'full' : ($span === 2 ? 'span-2' : '');
                        $val = holidayFieldValue($row, $field);
                        if ($name === 'department_id' && ($val === null || $val === '')) {
                            $val = '0';
                        }
                        ?>
                        <div class="form-group <?php echo $spanClass; ?>">
                            <label>
                                <?php echo htmlspecialchars($field['label']); ?>
                                <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
                            </label>
                            <?php if ($type === 'select'): ?>
                                <select name="<?php echo htmlspecialchars($name); ?>" class="form-control" <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                    <?php foreach (($field['options'] ?? []) as $optVal => $optLabel): ?>
                                        <option value="<?php echo htmlspecialchars((string) $optVal); ?>" <?php echo ((string) $val === (string) $optVal) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $optLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($name); ?>" class="form-control" rows="3"><?php echo htmlspecialchars((string) $val); ?></textarea>
                            <?php elseif ($type === 'date'): ?>
                                <input type="text" name="<?php echo htmlspecialchars($name); ?>" class="form-control js-date"
                                       placeholder="DD-MM-YYYY"
                                       value="<?php echo htmlspecialchars(dateInputValue($val)); ?>"
                                       <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php else: ?>
                                <input type="<?php echo $type === 'number' ? 'number' : 'text'; ?>"
                                       name="<?php echo htmlspecialchars($name); ?>"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars((string) $val); ?>"
                                       <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php endif; ?>
                            <?php if (!empty($field['help'])): ?>
                                <small class="form-hint"><?php echo htmlspecialchars($field['help']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Holiday
                </button>
                <a href="<?php echo htmlspecialchars($masterListUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
