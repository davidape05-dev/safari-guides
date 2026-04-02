// Show section
function showSection(event, sectionId) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Remove active from menu items
    document.querySelectorAll('.sidebar-menu li').forEach(item => {
        item.classList.remove('active');
    });

    // Show selected section
    document.getElementById(sectionId).classList.add('active');

    // Add active to clicked menu item
     event.target.closest('li').classList.add('active');
}

// Verify guide
function verifyGuide(guideId, action) {
    if (!confirm(`Are you sure you want to ${action} this guide?`)) {
        return;
    }

    fetch('admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&guide_id=${guideId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Action failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

// View guide details
function viewGuideDetails(guideId) {
    fetch('admin_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_details&guide_id=${guideId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const guide = data.guide;
            const modalContent = `
                <h2>${guide.first_name} ${guide.last_name}</h2>
                <img src="${guide.profile_photo ? 'uploads/profiles/' + guide.profile_photo : 'https://via.placeholder.com/200'}" 
                     style="width: 200px; height: 200px; border-radius: 50%; object-fit: cover; display: block; margin: 1rem auto;">
                <p><strong>Email:</strong> ${guide.email}</p>
                <p><strong>Phone:</strong> ${guide.phone}</p>
                <p><strong>License:</strong> ${guide.license_number}</p>
                <p><strong>Experience:</strong> ${guide.years_experience} years</p>
                <p><strong>Location:</strong> ${guide.location}</p>
                <p><strong>Price:</strong> KES ${guide.price_per_day}/day</p>
                <p><strong>Categories:</strong> ${guide.categories}</p>
                <p><strong>Languages:</strong> ${guide.languages}</p>
                <p><strong>Bio:</strong> ${guide.bio}</p>
                <div style="margin-top: 1.5rem;">
                    <button class="btn btn-primary" onclick="verifyGuide(${guideId}, 'approve'); closeModal();">Approve</button>
                    <button class="btn btn-danger" onclick="verifyGuide(${guideId}, 'reject'); closeModal();">Reject</button>
                </div>
            `;
            document.getElementById('modalContent').innerHTML = modalContent;
            document.getElementById('guideModal').classList.add('active');
        }
    });
}

// Suspend guide
function suspendGuide(guideId) {
    verifyGuide(guideId, 'suspend');
}

// Activate guide
function activateGuide(guideId) {
    verifyGuide(guideId, 'activate');
}

// Close modal
function closeModal() {
    document.getElementById('guideModal').classList.remove('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('guideModal');
    if (event.target === modal) {
        modal.classList.remove('active');
    }
}
