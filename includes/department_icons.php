<?php
/**
 * Department dashboard icons — unique FA6 solid icons + colors by department name
 */

function departmentIconCatalog()
{
    return [
        'ADMINISTRATION' => ['fa-landmark', '#5B6CFF'],
        'MANAGEMENT' => ['fa-briefcase', '#5B6CFF'],
        'HUMAN RESOURCE MANAGEMENT' => ['fa-users', '#E85D75'],
        'HUMAN RESOURCE' => ['fa-users', '#E85D75'],
        'HR & ADMIN' => ['fa-user-tie', '#E85D75'],
        'HR AND ADMIN' => ['fa-user-tie', '#E85D75'],
        'ACCOUNTS AND FINANCE' => ['fa-file-invoice-dollar', '#2ECC71'],
        'ACCOUNT' => ['fa-file-invoice-dollar', '#2ECC71'],
        'ACCOUNTS' => ['fa-file-invoice-dollar', '#2ECC71'],
        'COLLECTION' => ['fa-hand-holding-dollar', '#F39C12'],
        'SALES & MARKETING - BACK OFFICE' => ['fa-headset', '#9B59B6'],
        'SALES & MARKETING - ON FIELD' => ['fa-handshake', '#1ABC9C'],
        'IT AND NETWORKING' => ['fa-network-wired', '#3498DB'],
        'INFORMATION TECHNOLOGY' => ['fa-laptop-code', '#3498DB'],
        'TENDER' => ['fa-file-contract', '#E67E22'],
        'QA AND QC' => ['fa-clipboard-check', '#16A085'],
        'NPD' => ['fa-lightbulb', '#F1C40F'],
        'DESIGN' => ['fa-ruler-combined', '#8E44AD'],
        'PRODUCTION' => ['fa-industry', '#E74C3C'],
        'PURCHASE' => ['fa-cart-shopping', '#2980B9'],
        'STORE' => ['fa-warehouse', '#D35400'],
        'LABORATORY' => ['fa-flask', '#27AE60'],
        'CORE' => ['fa-cubes', '#7F8C8D'],
        'MELTING 1' => ['fa-fire', '#C0392B'],
        'MELTING 2' => ['fa-fire-flame-curved', '#E74C3C'],
        'CUTTING' => ['fa-scissors', '#34495E'],
        'GRINDING' => ['fa-gear', '#95A5A6'],
        'LATHE' => ['fa-gears', '#2C3E50'],
        'CNC' => ['fa-microchip', '#1ABC9C'],
        'BUFF' => ['fa-sparkles', '#F39C12'],
        'CLEANING' => ['fa-broom', '#3498DB'],
        'ASSEMBLY 1 & COATING' => ['fa-layer-group', '#9B59B6'],
        'ASSEMBLY 2' => ['fa-object-group', '#8E44AD'],
        'ASSEMBLY 3 RRL & FLEXIBLE' => ['fa-diagram-project', '#6C5CE7'],
        'ASSEMBLY 4 ALARM & DELUGE VALVE' => ['fa-bell', '#E17055'],
        'BUTTERFLY VALVE' => ['fa-circle-dot', '#00B894'],
        'SPRINKLER' => ['fa-shower', '#00CEC9'],
        'ARGON' => ['fa-atom', '#0984E3'],
        'MAINTENANCE' => ['fa-wrench', '#FD79A8'],
        'CANTEEN' => ['fa-utensils', '#FDCB6E'],
        'DISPATCH' => ['fa-truck', '#00B894'],
        'TRANSPORT' => ['fa-truck-fast', '#636E72'],
        'SECURITY' => ['fa-shield-halved', '#2D3436'],
        'QUALITY' => ['fa-certificate', '#16A085'],
        'PACKING' => ['fa-box', '#E17055'],
        'FOUNDRY' => ['fa-industry', '#C0392B'],
    ];
}

function resolveDepartmentIcon($departmentName)
{
    $name = strtoupper(trim(html_entity_decode((string) $departmentName)));
    $name = preg_replace('/\s+/', ' ', $name);
    $catalog = departmentIconCatalog();

    if (isset($catalog[$name])) {
        return $catalog[$name];
    }

    // Partial / alias match
    foreach ($catalog as $key => $meta) {
        if ($name !== '' && (strpos($name, $key) !== false || strpos($key, $name) !== false)) {
            return $meta;
        }
    }

    // Keyword fallbacks so sync-created depts never stay plain building
    $keywords = [
        'LATHE' => ['fa-gears', '#2C3E50'],
        'CNC' => ['fa-microchip', '#1ABC9C'],
        'BUFF' => ['fa-sparkles', '#F39C12'],
        'CLEAN' => ['fa-broom', '#3498DB'],
        'ASSEMB' => ['fa-layer-group', '#9B59B6'],
        'ACCOUNT' => ['fa-file-invoice-dollar', '#2ECC71'],
        'HR' => ['fa-users', '#E85D75'],
        'HUMAN' => ['fa-users', '#E85D75'],
        'IT ' => ['fa-laptop-code', '#3498DB'],
        'TECH' => ['fa-laptop-code', '#3498DB'],
        'MAINT' => ['fa-wrench', '#FD79A8'],
        'CANTEEN' => ['fa-utensils', '#FDCB6E'],
        'DISPATCH' => ['fa-truck', '#00B894'],
        'TRANSPORT' => ['fa-truck-fast', '#636E72'],
        'STORE' => ['fa-warehouse', '#D35400'],
        'PURCHASE' => ['fa-cart-shopping', '#2980B9'],
        'GRIND' => ['fa-gear', '#95A5A6'],
        'CUT' => ['fa-scissors', '#34495E'],
        'MELT' => ['fa-fire', '#C0392B'],
        'VALVE' => ['fa-circle-dot', '#00B894'],
        'SPRINK' => ['fa-shower', '#00CEC9'],
        'ARGON' => ['fa-atom', '#0984E3'],
        'ADMIN' => ['fa-landmark', '#5B6CFF'],
        'MANAGE' => ['fa-briefcase', '#5B6CFF'],
        'SECURITY' => ['fa-shield-halved', '#2D3436'],
        'QUALITY' => ['fa-clipboard-check', '#16A085'],
        'PACK' => ['fa-box', '#E17055'],
        'LAB' => ['fa-flask', '#27AE60'],
        'DESIGN' => ['fa-ruler-combined', '#8E44AD'],
        'PRODUCT' => ['fa-industry', '#E74C3C'],
    ];
    foreach ($keywords as $needle => $meta) {
        if (strpos($name, $needle) !== false) {
            return $meta;
        }
    }

    // Stable unique color from name hash (never all same blue building)
    $palette = ['#5B6CFF', '#E85D75', '#2ECC71', '#F39C12', '#9B59B6', '#1ABC9C', '#3498DB', '#E67E22', '#16A085', '#8E44AD', '#E74C3C', '#2980B9'];
    $color = $palette[abs(crc32($name)) % count($palette)];
    return ['fa-building-user', $color];
}

/**
 * Rewrite department icons to catalog (FA6-safe). Returns updated row count.
 */
function syncDepartmentIcons($conn = null)
{
    $closeAfter = false;
    if ($conn === null) {
        $conn = getDBConnection();
        $closeAfter = true;
    }

    // Fix known outdated FA5 class names still stored in DB
    $renames = [
        'fa-hand-holding-usd' => 'fa-hand-holding-dollar',
        'fa-shopping-cart' => 'fa-cart-shopping',
        'fa-pencil-ruler' => 'fa-ruler-combined',
    ];
    foreach ($renames as $old => $new) {
        $oldEsc = $conn->real_escape_string($old);
        $newEsc = $conn->real_escape_string($new);
        $conn->query("UPDATE departments SET icon_class = '{$newEsc}' WHERE icon_class = '{$oldEsc}'");
    }

    $updated = 0;
    $res = $conn->query('SELECT id, department_name, icon_class, icon_color FROM departments WHERE status = 1');
    if ($res) {
        $stmt = $conn->prepare('UPDATE departments SET icon_class = ?, icon_color = ? WHERE id = ?');
        while ($row = $res->fetch_assoc()) {
            [$icon, $color] = resolveDepartmentIcon($row['department_name']);
            $curIcon = (string) ($row['icon_class'] ?? '');
            $curColor = (string) ($row['icon_color'] ?? '');
            if ($curIcon !== $icon || $curColor !== $color) {
                $id = (int) $row['id'];
                $stmt->bind_param('ssi', $icon, $color, $id);
                $stmt->execute();
                $updated++;
            }
        }
        $stmt->close();
    }

    if ($closeAfter) {
        $conn->close();
    }
    return $updated;
}
