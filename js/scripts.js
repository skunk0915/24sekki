document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.getElementById('menuBtn');
    const kouList = document.getElementById('kouList');
    const closeMenu = document.getElementById('closeMenu');
    if(menuBtn && kouList && closeMenu) {
        menuBtn.addEventListener('click', function() {
            kouList.classList.remove('hidden');
        });
        closeMenu.addEventListener('click', function() {
            kouList.classList.add('hidden');
        });
    }
});
