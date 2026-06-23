<?php
/**
 * Shared Sidebar Layout Template
 * Crown Basketball Academy
 */

// If header has not resolved active page name, resolve here
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']);
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isCoach = isset($_SESSION['role']) && $_SESSION['role'] === 'coach';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <svg viewBox="0 0 24 24" style="fill: none !important; stroke: #ffffff !important; stroke-width: 2px !important; stroke-linecap: round; stroke-linejoin: round;">
                <circle cx="12" cy="12" r="10" />
                <path d="M6.2 6.2c2.4 2.4 2.4 6.4 0 8.8" />
                <path d="M17.8 6.2c-2.4 2.4-2.4 6.4 0 8.8" />
                <path d="M2 12h20" />
                <path d="M12 2v20" />
            </svg>
        </div>
        <span class="brand-text">CROWN ACADEMY</span>
    </div>
    
    <ul class="sidebar-menu">
        <!-- Dashboard Link -->
        <li class="menu-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <a href="dashboard.php">
                <!-- Home Icon -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        
        <!-- Participants Link (All roles can see list, edit options based on role checked inside files) -->
        <li class="menu-item <?= in_array($currentPage, ['participants.php', 'participant-add.php', 'participant-edit.php', 'participant-detail.php']) ? 'active' : '' ?>">
            <a href="participants.php">
                <!-- Users Icon -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="menu-text">Data Peserta</span>
            </a>
        </li>
        
        <!-- Coaches Link (Admin Only) -->
        <?php if ($isAdmin): ?>
        <li class="menu-item <?= in_array($currentPage, ['coaches.php', 'coach-add.php', 'coach-edit.php']) ? 'active' : '' ?>">
            <a href="coaches.php">
                <!-- Whistle/User-Tie Icon -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="4"/>
                    <line x1="8" y1="2" x2="8" y2="4"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span class="menu-text">Data Pelatih</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Participant Attendances Link (Admin & Coach) -->
        <li class="menu-item <?= in_array($currentPage, ['attendance-participants.php', 'attendance-participants-history.php']) ? 'active' : '' ?>">
            <a href="attendance-participants.php">
                <!-- Calendar Check Icon -->
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                    <path d="M9 16l2 2 4-4"/>
                </svg>
                <span class="menu-text">Absensi Peserta</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="padding: 0; margin-bottom: 0;">
            <!-- Profile Link -->
            <li class="menu-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <a href="profile.php">
                    <!-- User Icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span class="menu-text">Profil Saya</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="logout.php">
                    <!-- Sign-Out Icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span class="menu-text">Keluar</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
