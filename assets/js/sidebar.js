// Sidebar JavaScript functionality
function initializeSidebar() {
    console.log('Sidebar: Initializing...');
    
    // Handle submenu toggles
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    console.log('Sidebar: Found', submenuToggles.length, 'submenu toggles');
    
    // Remove any existing event listeners first
    submenuToggles.forEach(toggle => {
        // Clone the element to remove all event listeners
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
    });
    
    // Get the fresh elements after cloning
    const freshToggles = document.querySelectorAll('.submenu-toggle');
    
    freshToggles.forEach((toggle, index) => {
        console.log('Sidebar: Setting up toggle', index);
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Sidebar: Toggle clicked');
            
            const parentLi = this.closest('li.has-submenu');
            const submenu = parentLi ? parentLi.querySelector('.submenu') : null;
            const arrow = this.querySelector('.submenu-arrow');
            
            if (!parentLi || !submenu) {
                console.error('Sidebar: Missing required elements', {parentLi, submenu});
                return;
            }
            
            // Close all other submenus first
            freshToggles.forEach(otherToggle => {
                if (otherToggle !== this) {
                    const otherParent = otherToggle.closest('li.has-submenu');
                    const otherSubmenu = otherParent ? otherParent.querySelector('.submenu') : null;
                    const otherArrow = otherToggle.querySelector('.submenu-arrow');
                    
                    if (otherParent && otherSubmenu) {
                        otherParent.classList.remove('open');
                        otherSubmenu.style.maxHeight = '0';
                        otherSubmenu.style.opacity = '0';
                    }
                    if (otherArrow) {
                        otherArrow.style.transform = 'rotate(0deg)';
                    }
                }
            });
            
            // Toggle current submenu
            if (parentLi.classList.contains('open')) {
                console.log('Sidebar: Closing submenu');
                parentLi.classList.remove('open');
                submenu.style.maxHeight = '0';
                submenu.style.opacity = '0';
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            } else {
                console.log('Sidebar: Opening submenu');
                parentLi.classList.add('open');
                // Set max height to content height
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                submenu.style.opacity = '1';
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            }
        });
    });
    
    // Auto-open submenu if current page is in it
    const activeSubmenus = document.querySelectorAll('.has-submenu.active');
    console.log('Sidebar: Found', activeSubmenus.length, 'active submenus');
    
    activeSubmenus.forEach(parentLi => {
        const submenu = parentLi.querySelector('.submenu');
        const arrow = parentLi.querySelector('.submenu-arrow');
        
        console.log('Sidebar: Auto-opening submenu for active item');
        parentLi.classList.add('open');
        if (submenu) {
            submenu.style.maxHeight = submenu.scrollHeight + 'px';
            submenu.style.opacity = '1';
        }
        if (arrow) {
            arrow.style.transform = 'rotate(180deg)';
        }
    });
    
    // Also check for active submenu items
    const activeSubmenuItems = document.querySelectorAll('.submenu a.active');
    console.log('Sidebar: Found', activeSubmenuItems.length, 'active submenu items');
    
    activeSubmenuItems.forEach(activeItem => {
        const parentSubmenu = activeItem.closest('.has-submenu');
        if (parentSubmenu && !parentSubmenu.classList.contains('open')) {
            const submenu = parentSubmenu.querySelector('.submenu');
            const arrow = parentSubmenu.querySelector('.submenu-arrow');
            
            console.log('Sidebar: Auto-opening parent submenu for active item');
            parentSubmenu.classList.add('open');
            if (submenu) {
                submenu.style.maxHeight = submenu.scrollHeight + 'px';
                submenu.style.opacity = '1';
            }
            if (arrow) {
                arrow.style.transform = 'rotate(180deg)';
            }
        }
    });
    
    // Sidebar toggle for mobile/desktop with overlay & ARIA
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    function openSidebarMobile(){
        sidebar.classList.add('show');
        if (overlay) overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded','true');
    }
    function closeSidebarMobile(){
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded','false');
    }
    function isMobile(){ return window.innerWidth <= 768; }

    function handleMobileTriggerClick(e){
        e.preventDefault();
        if (isMobile()) {
            if (sidebar.classList.contains('show')) closeSidebarMobile(); else openSidebarMobile();
        } else if (e.currentTarget === sidebarToggle) {
            // Desktop only allows collapsing via sidebarToggle
            const nowCollapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed', nowCollapsed);
        }
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', handleMobileTriggerClick);
    }
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', handleMobileTriggerClick);
    }
    if (overlay) {
        overlay.addEventListener('click', function(){ closeSidebarMobile(); });
    }
    
    console.log('Sidebar: Initialization complete');
}

// Initialize on DOM content loaded
document.addEventListener('DOMContentLoaded', initializeSidebar);

// Also initialize after a short delay to ensure all elements are loaded
setTimeout(initializeSidebar, 100);

// Re-initialize on hash change (for SPA-like behavior)
window.addEventListener('hashchange', function() {
    setTimeout(initializeSidebar, 50);
});

// Re-initialize on page visibility change
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        setTimeout(initializeSidebar, 50);
    }
});

// Responsive rule: collapse (icon-only) below 769px, fully expanded at >= 769px (desktop)
function applyResponsiveSidebarState() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const width = window.innerWidth;
    if (width < 769) {
        // Below 769: we want the sidebar in collapsed (icon) mode by default (unless explicitly opened as off-canvas)
        if (!sidebar.classList.contains('collapsed') && !sidebar.classList.contains('show')) {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
        // If user manually opened off-canvas (.show), ensure collapsed styling doesn't interfere
        if (sidebar.classList.contains('show')) {
            sidebar.classList.remove('collapsed');
            document.body.classList.remove('sidebar-collapsed');
        }
    } else {
        // >= 769px always expanded
        sidebar.classList.remove('collapsed');
        document.body.classList.remove('sidebar-collapsed');
        // Also ensure off-canvas class removed (desktop shouldn't use .show)
        sidebar.classList.remove('show');
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.remove('show');
    }
}

window.addEventListener('resize', applyResponsiveSidebarState);
document.addEventListener('DOMContentLoaded', applyResponsiveSidebarState);
