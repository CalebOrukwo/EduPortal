<?php
/**
 * Shared Footer Component
 * File: includes/footer.php
 */
?>
    </main>

    

    <!-- Global Dynamic UI Scripts -->
    <script>
        // Alert Banner Dismissal Helper
        document.querySelectorAll('.alert-dismiss-btn').forEach(button => {
            button.addEventListener('click', () => {
                const alertBox = button.closest('.alert-banner');
                if (alertBox) {
                    alertBox.style.opacity = '0';
                    alertBox.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => alertBox.remove(), 300);
                }
            });
        });

        // Modal Controls
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        // Sidebar Toggle Helper
        function toggleSidebar(sidebarId = 'main-sidebar') {
            const sidebar = document.getElementById(sidebarId);
            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
            }
        }
    </script>
</body>
</html>