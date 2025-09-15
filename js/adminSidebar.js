/**
 * Admin Sidebar Functionality
 * Handles sidebar toggling and active state management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const burgerBtn = document.getElementById('burger-btn');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // Check if elements exist before adding event listeners
    if (burgerBtn && sidebar) {
        burgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            // Store sidebar state in localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            
            // Update burger button icon
            this.textContent = isCollapsed ? '☰' : '✕';
        });
        
        // Initialize sidebar state from localStorage
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            burgerBtn.textContent = '☰';
        }
    }
    
    // Highlight active menu item
    const currentPage = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('#sidebar .dropdown-item, #sidebar .btn-crimson');
    
    menuItems.forEach(item => {
        const itemHref = item.getAttribute('href');
        if (itemHref && itemHref.includes(currentPage)) {
            item.classList.add('active');
            
            // If it's in a dropdown, open the dropdown
            const dropdown = item.closest('.dropdown-menu');
            if (dropdown) {
                const dropdownToggle = dropdown.previousElementSibling;
                if (dropdownToggle && dropdownToggle.classList.contains('dropdown-toggle')) {
                    dropdownToggle.classList.add('active');
                }
            }
        }
    });
    
    // Dropdown functionality
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdownMenu = this.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                dropdownMenu.classList.toggle('show');
                this.setAttribute('aria-expanded', dropdownMenu.classList.contains('show'));
            }
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.setAttribute('aria-expanded', 'false');
            });
        }
    });
});