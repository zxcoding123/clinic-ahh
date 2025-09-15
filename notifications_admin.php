<?php
// this must be here lol
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* Notification Bell */
  .notification-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
  }

  .notification-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #dc3545;
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    font-size: 24px;
    transition: all 0.3s ease;
  }

  .notification-btn:hover {
    background-color: #c82333;
    transform: scale(1.1);
  }

  .notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ffc107;
    color: #212529;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
  }

  .notification-dropdown {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 350px;
    max-height: 500px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: none;
    flex-direction: column;
    overflow: hidden;
  }

  .notification-icon {
    background-color: #f0f0f0;
    /* light gray background */
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    color: #555;
  }

  .notification-item.unread .notification-icon {
    background-color: #ffebcc;
    /* light orange for unread */
    color: #ff9800;
    /* orange icon */
  }

  .notification-dropdown.show {
    display: flex;
  }

  .notification-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .notification-header h5 {
    margin: 0;
    font-weight: bold;
  }

  .mark-all-read {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-size: 13px;
  }

  .notification-list {
    overflow-y: auto;
    flex-grow: 1;
  }

  .notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
    transition: background-color 0.2s;
  }

  .notification-item.unread {
    background-color: #f8f9fa;
    font-weight: 500;
  }

  .notification-item:hover {
    background-color: #f0f0f0;
  }

  .notification-title {
    display: flex;
  }

  .notification-item .notification-title {
    font-weight: bold;
    margin-bottom: 5px;
  }

  .notification-item .notification-time {
    font-size: 12px;
    color: #6c757d;
  }

  .notification-footer {
    padding: 10px 15px;
    text-align: center;
    border-top: 1px solid #eee;
  }

  .notification-footer a {
    color: #6c757d;
    text-decoration: none;
  }

  .notification-footer a:hover {
    color: #007bff;
  }
</style>

<!-- Notification Bell -->
<div class="notification-container">
  <button class="notification-btn" id="notificationBtn">
    <i class="bi bi-bell"></i>
    <span class="notification-badge" id="notificationBadge">0</span>
  </button>
  <div class="notification-dropdown" id="notificationDropdown">
    <div class="notification-header">
      <h5>Notifications</h5>
      <button class="mark-all-read" id="markAllRead">Mark all as read</button>
    </div>
    <div class="notification-list" id="notificationList">
      <!-- Notifications will be loaded here -->
    </div>
    <div class="notification-footer">
      <a href="#" data-bs-toggle="modal" data-bs-target="#notificationsModal">
        View All
      </a>
    </div>

  </div>
</div>

<div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificationsModalLabel">All Notifications</button></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modalNotificationList"></div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.getElementById('markAllRead');

    // Toggle dropdown visibility
    notificationBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      notificationDropdown.classList.toggle('show');
      if (notificationDropdown.classList.contains('show')) {
        loadNotifications();
      }
    });

    function loadModalNotifications() {
      const modalList = document.getElementById('modalNotificationList');
      modalList.innerHTML = '<p>Loading notifications...</p>';

      fetch('get_notifications_admin.php')
        .then(response => response.json())
        .then(data => {
          if (data.notifications.length === 0) {
            modalList.innerHTML = '<p>No notifications</p>';
            return;
          }

          modalList.innerHTML = '';
          data.notifications.forEach(notification => {
            const item = document.createElement('div');
            item.className = `notification-item ${notification.status === 'unread' ? 'unread' : ''}`;
            item.innerHTML = `
                    <div class="notification-title">
                        <i class="bi bi-bell me-2 text-warning notification-icon"></i>
                        ${notification.title}
                    </div>
                    <div class="notification-message">${notification.description}</div>
                    <div class="notification-time">${formatTime(notification.created_at)}</div>
                `;
            item.addEventListener('click', () => {
              markAsRead(notification.id);
              window.location.href = notification.link || '#';
            });
            modalList.appendChild(item);
          });
        })
        .catch(error => {
          modalList.innerHTML = '<p>Error loading notifications</p>';
          console.error('Error:', error);
        });

          fetch('mark_all_read_admin.php', {
          method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
         
            loadNotifications();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Failed to load notifications.',
              text: data.message || 'Please try again later.'
            });
          }
        })
        .catch(error => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Something went wrong. Please try again.'
          });
          console.error(error);
        });
  
    }

    document.getElementById('notificationsModal').addEventListener('show.bs.modal', function() {
      loadModalNotifications();
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!notificationDropdown.contains(e.target) && e.target !== notificationBtn) {
        notificationDropdown.classList.remove('show');
      }
    });

    // Load notifications from server
    function loadNotifications() {
      fetch('get_notifications_admin.php')
        .then(response => response.json())
        .then(data => {
          updateNotificationBadge(data.unread_count);
          renderNotifications(data.notifications);
        })
        .catch(error => console.error('Error loading notifications:', error));
    }

    // Render notifications in dropdown
    function renderNotifications(notifications) {
      notificationList.innerHTML = '';

      if (notifications.length === 0) {
        notificationList.innerHTML = '<div class="notification-item">No notifications</div>';
        return;
      }

      notifications.forEach(notification => {
        const item = document.createElement('div');
        item.className = `notification-item ${notification.status === 'unread' ? 'unread' : ''}`;
        item.innerHTML = `
      <div class="notification-title">
        <i class="bi bi-bell me-2 text-warning notification-icon"></i>${notification.title}
      </div>
      <div class="notification-message">${notification.description}</div>
      <div class="notification-time">${formatTime(notification.created_at)}</div>
    `;
        item.addEventListener('click', () => {
          markAsRead(notification.id);
          window.location.href = notification.link || '#';
        });
        notificationList.appendChild(item);
      });
    }


    // Update badge count
    function updateNotificationBadge(count) {
      notificationBadge.textContent = count;
      if (count > 0) {
        notificationBadge.style.display = 'flex';
      } else {
        notificationBadge.style.display = 'none';
      }
    }

    // Format time
    function formatTime(timestamp) {
      const date = new Date(timestamp);
      return date.toLocaleString();
    }

    // Mark notification as read
    function markAsRead(notificationId) {
      fetch('mark_as_read_admin.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            id: notificationId
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            loadNotifications();
          }
        });
    }

    // Mark all as read
    markAllReadBtn.addEventListener('click', function(e) {
      e.stopPropagation();

      fetch('mark_all_read_admin.php', {
          method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              icon: 'success',
              title: 'All notifications marked as read',
              showConfirmButton: false,
              timer: 1500
            });
            loadNotifications();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Failed to mark all as read',
              text: data.message || 'Please try again later.'
            });
          }
        })
        .catch(error => {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Something went wrong. Please try again.'
          });
          console.error(error);
        });
    });

    // Initial load of notification count
    fetch('get_notification_count_admin.php')
      .then(response => response.json())
      .then(data => updateNotificationBadge(data.unread_count))
      .catch(error => console.error('Error loading notification count:', error));
  });
</script>