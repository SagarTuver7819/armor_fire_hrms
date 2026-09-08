<?php
/**
 * Shared Master Add/Edit Form
 * Requires $masterKey set before include.
 */

require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
if ($id > 0) {
    $row = getMasterRow($master['table'], $id);
    if (!$row || (int) ($row['status'] ?? 1) !== 1) {
        header('Location: ' . $masterListUrl);
        exit;
    }
}

$pageTitle = ($row ? 'Edit ' : 'Add ') . $master['singular'];

$useSidebar = true;
$sidebarMode = 'masters';
$sidebarActive = $master['key'];

require_once __DIR__ . '/../../includes/header.php';

function masterFieldValue($row, $field)
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
                    <h1><?php echo $row ? 'Edit' : 'Add'; ?> <?php echo htmlspecialchars($master['singular']); ?></h1>
                    <p><?php echo htmlspecialchars($master['title']); ?></p>
                </div>
            </div>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($masterSaveUrl); ?>" class="employee-form master-form" autocomplete="off">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <div class="form-section">
                <h3><i class="fa-solid fa-pen-to-square"></i> Details</h3>
                <div class="form-grid form-grid-3">
                    <?php foreach ($master['fields'] as $field): ?>
                        <?php
                        $span = (int) ($field['span'] ?? 1);
                        $spanClass = $span === 3 ? 'full' : ($span === 2 ? 'span-2' : '');
                        $val = masterFieldValue($row, $field);
                        $req = !empty($field['required']) ? 'required' : '';
                        ?>
                        <div class="form-group <?php echo $spanClass; ?>">
                            <label>
                                <?php echo htmlspecialchars($field['label']); ?>
                                <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
                            </label>

                            <?php if ($field['type'] === 'textarea'): ?>
                                <textarea name="<?php echo htmlspecialchars($field['name']); ?>"
                                          class="form-control" rows="3" <?php echo $req; ?>><?php echo htmlspecialchars((string) $val); ?></textarea>

                            <?php elseif ($field['type'] === 'select'): ?>
                                <select name="<?php echo htmlspecialchars($field['name']); ?>" class="form-control" <?php echo $req; ?>>
                                    <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                                        <option value="<?php echo htmlspecialchars((string) $optVal); ?>"
                                            <?php echo ((string) $val === (string) $optVal) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($optLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($field['type'] === 'date'): ?>
                                <input type="text"
                                       name="<?php echo htmlspecialchars($field['name']); ?>"
                                       class="form-control js-date"
                                       placeholder="DD-MM-YYYY"
                                       <?php echo $req; ?>
                                       value="<?php echo htmlspecialchars(dateInputValue($val)); ?>">

                            <?php elseif ($field['type'] === 'color'): ?>
                                <div class="color-input-row">
                                    <input type="color" class="form-control color-picker"
                                           value="<?php echo htmlspecialchars($val ?: '#F58220'); ?>"
                                           oninput="this.nextElementSibling.value=this.value">
                                    <input type="text" name="<?php echo htmlspecialchars($field['name']); ?>"
                                           class="form-control" <?php echo $req; ?>
                                           value="<?php echo htmlspecialchars((string) ($val ?: '#F58220')); ?>">
                                </div>

                            <?php else: ?>
                                <input type="<?php echo htmlspecialchars($field['type']); ?>"
                                       name="<?php echo htmlspecialchars($field['name']); ?>"
                                       class="form-control"
                                       <?php echo $req; ?>
                                       <?php if (!empty($field['step'])): ?>step="<?php echo htmlspecialchars($field['step']); ?>"<?php endif; ?>
                                       value="<?php echo htmlspecialchars((string) $val); ?>">
                            <?php endif; ?>

                            <?php if (!empty($field['help'])): ?>
                                <small class="form-help"><?php echo htmlspecialchars($field['help']); ?></small>
                            <?php elseif ($field['type'] === 'date'): ?>
                                <small class="form-help">Format: DD-MM-YYYY</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sticky-actions form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?php echo $row ? 'Update' : 'Save'; ?>
                </button>
                <a href="<?php echo htmlspecialchars($masterListUrl); ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
