/**
 * BHELA Houseboat — Gallery Lightbox
 *
 * @package BhelaHouseboat
 */

document.addEventListener('DOMContentLoaded', function () {
    const galleryItems = document.querySelectorAll('.gallery-item');
    if (!galleryItems.length) return;

    // Create lightbox
    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.id = 'gallery-lightbox';
    lightbox.innerHTML = `
        <button class="lightbox__close" aria-label="Close">✕</button>
        <button class="lightbox__nav lightbox__nav--prev" aria-label="Previous">❮</button>
        <img class="lightbox__img" src="" alt="" />
        <button class="lightbox__nav lightbox__nav--next" aria-label="Next">❯</button>
    `;
    document.body.appendChild(lightbox);

    const lightboxImg = lightbox.querySelector('.lightbox__img');
    const closeBtn = lightbox.querySelector('.lightbox__close');
    const prevBtn = lightbox.querySelector('.lightbox__nav--prev');
    const nextBtn = lightbox.querySelector('.lightbox__nav--next');

    let currentIndex = 0;
    const images = [];

    galleryItems.forEach(function (item, index) {
        const img = item.querySelector('img');
        if (img) {
            images.push({
                src: img.dataset.full || img.src,
                alt: img.alt,
            });

            item.addEventListener('click', function () {
                currentIndex = index;
                openLightbox();
            });
        }
    });

    function openLightbox() {
        lightboxImg.src = images[currentIndex].src;
        lightboxImg.alt = images[currentIndex].alt;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }

    function prevImage() {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        lightboxImg.src = images[currentIndex].src;
        lightboxImg.alt = images[currentIndex].alt;
    }

    function nextImage() {
        currentIndex = (currentIndex + 1) % images.length;
        lightboxImg.src = images[currentIndex].src;
        lightboxImg.alt = images[currentIndex].alt;
    }

    closeBtn.addEventListener('click', closeLightbox);
    prevBtn.addEventListener('click', prevImage);
    nextBtn.addEventListener('click', nextImage);

    lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function (e) {
        if (!lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') prevImage();
        if (e.key === 'ArrowRight') nextImage();
    });
});
