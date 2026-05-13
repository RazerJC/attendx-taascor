<?php
/**
 * Shared Footer Include
 */
$user = currentUser();
?>
<?php if ($user): ?>
        </main><!-- /Page Content -->
    </div><!-- /Main Content -->
</div><!-- /appLayout -->
<?php endif; ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Theme toggle
function toggleTheme() {
    const html = document.documentElement;
    const isLight = html.classList.toggle('light-mode');
    localStorage.setItem('taascor_theme', isLight ? 'light' : 'dark');
    updateThemeIcons();
}

function updateThemeIcons() {
    const isLight = document.documentElement.classList.contains('light-mode');
    const sun = document.getElementById('iconSun');
    const moon = document.getElementById('iconMoon');
    if (sun && moon) {
        sun.classList.toggle('hidden', !isLight);
        moon.classList.toggle('hidden', isLight);
    }
}

// Auto-dismiss flash
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flashMsg');
    if (flash) setTimeout(() => flash.remove(), 4000);
    updateThemeIcons();
});
</script>
</body>
</html>
