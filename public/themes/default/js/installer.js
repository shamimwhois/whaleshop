document.addEventListener('click', function (e) {
    const modalShowButton = e.target.closest('.modal-show');
    const modalCloseButton = e.target.closest('.modal-close');


    if (modalShowButton) {
        document.getElementById('installer-modal').classList.add('active');
    } else if (modalCloseButton) {
        document.getElementById('installer-modal').classList.remove('active');
    }
});