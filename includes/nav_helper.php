<?php
/**
 * Back-navigation helper
 * Remember the hub/module you entered from, and return there on Back.
 *
 * Usage:
 *   navRemember('modules', ['department_id' => $deptId]);  // on hub pages
 *   $back = navResolveBack(['department_id' => $deptId, 'fallback' => 'dashboard']);
 *   // $back = ['url' => '...', 'label' => 'Back to Modules']
 */

if (!function_exists('navRemember')) {

    /**
     * Allowed origin keys and their default destinations.
     */
    function navOriginMap()
    {
        return [
            'modules' => [
                'path' => 'department.php',
                'label' => 'Back to Modules',
                'needs_dept' => true,
            ],
            'attendance' => [
                'path' => 'attendance/index.php',
                'label' => 'Back to Attendance',
                'needs_dept' => false,
            ],
            'hr' => [
                'path' => 'hr/dashboard.php',
                'label' => 'Back to HR Dashboard',
                'needs_dept' => false,
            ],
            'employee' => [
                'path' => 'employee/dashboard.php',
                'label' => 'Back to Home',
                'needs_dept' => false,
            ],
            'contractor' => [
                'path' => 'contractor/index.php',
                'label' => 'Back to Contractor Hub',
                'needs_dept' => false,
            ],
            'masters' => [
                'path' => 'masters/index.php',
                'label' => 'Back to Masters',
                'needs_dept' => false,
            ],
            'roles' => [
                'path' => 'roles/index.php',
                'label' => 'Back to Roles',
                'needs_dept' => false,
            ],
            'dashboard' => [
                'path' => 'dashboard.php',
                'label' => 'Back to Dashboard',
                'needs_dept' => false,
            ],
            'leave' => [
                'path' => 'leave/index.php',
                'label' => 'Back to Leave Requests',
                'needs_dept' => false,
            ],
            'payroll_register' => [
                'path' => 'payroll/register.php',
                'label' => 'Back to Salary Register',
                'needs_dept' => false,
            ],
        ];
    }

    /**
     * Sanitize origin key from request / session.
     */
    function navSanitizeKey($key)
    {
        $key = strtolower(trim((string) $key));
        $map = navOriginMap();
        return isset($map[$key]) ? $key : '';
    }

    /**
     * Remember current hub so child module pages can Back here.
     *
     * @param string $key Origin key (modules, attendance, hr, …)
     * @param array  $opts Optional: department_id, query (extra QS), label, path override
     */
    function navRemember($key, array $opts = [])
    {
        $key = navSanitizeKey($key);
        if ($key === '') {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $map = navOriginMap();
        $meta = $map[$key];
        $deptId = (int) ($opts['department_id'] ?? 0);
        if (!empty($meta['needs_dept']) && $deptId <= 0) {
            return;
        }

        $path = (string) ($opts['path'] ?? $meta['path']);
        $query = [];
        if ($deptId > 0 && ($key === 'modules' || !empty($opts['keep_dept']))) {
            if ($key === 'modules') {
                $query['id'] = $deptId;
            } else {
                $query['department_id'] = $deptId;
            }
        }
        if (!empty($opts['query']) && is_array($opts['query'])) {
            $query = array_merge($query, $opts['query']);
        }
        $url = $path . ($query ? ('?' . http_build_query($query)) : '');
        $label = (string) ($opts['label'] ?? $meta['label']);

        $_SESSION['nav_origin'] = [
            'key' => $key,
            'url' => $url,
            'label' => $label,
            'department_id' => $deptId,
            'at' => time(),
        ];
    }

    /**
     * Read explicit ?from= key from request.
     */
    function navFromRequest()
    {
        return navSanitizeKey($_GET['from'] ?? '');
    }

    /**
     * Build absolute app URL + label for a known origin key.
     */
    function navBuildFromKey($key, $deptId = 0, array $extraQuery = [])
    {
        $key = navSanitizeKey($key);
        if ($key === '') {
            return null;
        }
        $map = navOriginMap();
        $meta = $map[$key];
        $deptId = (int) $deptId;
        if (!empty($meta['needs_dept']) && $deptId <= 0) {
            return null;
        }
        $query = $extraQuery;
        if ($key === 'modules') {
            $query['id'] = $deptId;
        } elseif ($deptId > 0 && !empty($extraQuery['keep_dept'])) {
            unset($query['keep_dept']);
            $query['department_id'] = $deptId;
        }
        if ($key === 'leave' && $deptId > 0) {
            $query['department_id'] = $deptId;
        }
        $url = $meta['path'] . ($query ? ('?' . http_build_query($query)) : '');
        return [
            'key' => $key,
            'url' => function_exists('app_url') ? app_url($url) : $url,
            'label' => $meta['label'],
            'department_id' => $deptId,
        ];
    }

    /**
     * Resolve where Back should go.
     *
     * Options:
     *   department_id  int
     *   from           string  force origin key (else GET from, else session)
     *   fallback       string  origin key if nothing else matches (default dashboard)
     *   prefer_dept    bool    if dept id set and no from/session, use modules (default true for dept modules)
     *   default_label  string
     *
     * @return array{url:string,label:string,key:string}
     */
    function navResolveBack(array $opts = [])
    {
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/../config/app.php';
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $deptId = (int) ($opts['department_id'] ?? 0);
        $fallback = navSanitizeKey($opts['fallback'] ?? 'dashboard') ?: 'dashboard';
        $preferDept = array_key_exists('prefer_dept', $opts) ? (bool) $opts['prefer_dept'] : true;

        // 1) Explicit from= (request or option)
        $from = navSanitizeKey($opts['from'] ?? '') ?: navFromRequest();
        if ($from !== '') {
            $built = navBuildFromKey($from, $deptId);
            if ($built) {
                return $built;
            }
        }

        // 2) Session remembered hub (fresh within 8 hours)
        $sess = $_SESSION['nav_origin'] ?? null;
        if (is_array($sess) && !empty($sess['key'])) {
            $age = time() - (int) ($sess['at'] ?? 0);
            if ($age >= 0 && $age < 28800) {
                $sessKey = navSanitizeKey($sess['key']);
                $sessDept = (int) ($sess['department_id'] ?? 0);
                if ($sessKey === 'modules') {
                    $useDept = ($deptId > 0) ? $deptId : $sessDept;
                    $built = navBuildFromKey('modules', $useDept);
                    if ($built) {
                        return $built;
                    }
                } elseif ($sessKey !== '') {
                    $extra = [];
                    if ($sessKey === 'leave' && ($deptId > 0 || $sessDept > 0)) {
                        $extra['department_id'] = $deptId > 0 ? $deptId : $sessDept;
                    }
                    $built = navBuildFromKey($sessKey, $deptId > 0 ? $deptId : $sessDept, $extra);
                    if ($built) {
                        if (!empty($sess['label'])) {
                            $built['label'] = (string) $sess['label'];
                        }
                        return $built;
                    }
                }
            }
        }

        // 3) Department-scoped page → department modules
        if ($preferDept && $deptId > 0) {
            $built = navBuildFromKey('modules', $deptId);
            if ($built) {
                return $built;
            }
        }

        // 4) Employee portal home when fallback employee / is employee without staff
        if ($fallback === 'employee' || ($fallback === 'hr' && function_exists('isEmployee') && isEmployee() && function_exists('isStaffUser') && !isStaffUser())) {
            // Office staff / employees → home; HR/Admin → hr if that's fallback
            if (function_exists('isEmployee') && isEmployee() && !(function_exists('isAdmin') && isAdmin()) && !(function_exists('isHR') && isHR())) {
                $built = navBuildFromKey('employee');
                if ($built) {
                    return $built;
                }
            }
        }

        $built = navBuildFromKey($fallback, $deptId);
        if ($built) {
            return $built;
        }
        return [
            'key' => 'dashboard',
            'url' => app_url('dashboard.php'),
            'label' => (string) ($opts['default_label'] ?? 'Back to Dashboard'),
            'department_id' => 0,
        ];
    }

    /**
     * Append from= to a relative path or existing query URL (for outbound links).
     */
    function navUrlWithFrom($path, $fromKey, array $query = [])
    {
        $fromKey = navSanitizeKey($fromKey);
        if ($fromKey !== '') {
            $query['from'] = $fromKey;
        }
        $path = (string) $path;
        if ($query) {
            $sep = (strpos($path, '?') !== false) ? '&' : '?';
            $path .= $sep . http_build_query($query);
        }
        return function_exists('app_url') ? app_url($path) : $path;
    }

    /**
     * Render a standard back-link anchor (HTML string).
     */
    function navBackLinkHtml(array $opts = [])
    {
        $back = navResolveBack($opts);
        return '<a href="' . htmlspecialchars($back['url']) . '" class="back-link">'
            . '<i class="fa-solid fa-arrow-left"></i> '
            . htmlspecialchars($back['label'])
            . '</a>';
    }
}
