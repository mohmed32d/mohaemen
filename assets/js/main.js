document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('scanForm')) {
        document.getElementById('scanForm').addEventListener('submit', function(e) {
            const target = document.getElementById('target').value;
            if (!target) {
                e.preventDefault();
                alert('Please enter a target URL or IP');
                return false;
            }
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = 'Scanning... <i class="fa fa-spinner fa-spin"></i>';
        });
    }
    
    document.querySelectorAll('.toggle-details').forEach(btn => {
        btn.addEventListener('click', function() {
            const details = this.nextElementSibling;
            details.style.display = details.style.display === 'none' ? 'block' : 'none';
            this.textContent = details.style.display === 'none' ? 'Show Details' : 'Hide Details';
        });
    });
    document.addEventListener('DOMContentLoaded', function() {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                navItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 20px rgba(0,0,0,0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        });
    
        const logoutBtn = document.querySelector('.logout-btn');
        if(logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                if(!confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                    e.preventDefault();
                }
            });
        }
    });
    if (document.querySelector('.tabs')) {
        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                
                document.querySelectorAll('.tab-link').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                document.getElementById(tabId).style.display = 'block';
                this.classList.add('active');
            });
        });
        
        document.querySelector('.tab-link').click();
    }
});
