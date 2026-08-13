/**
 * Dashboard animations - staggered cards + icon hover feel
 */
(function () {
    var cards = document.querySelectorAll('.module-card');
    if (!cards.length) return;

    // Stagger entrance
    cards.forEach(function (card, index) {
        card.style.setProperty('--i', index);
        card.classList.add('card-animate');
    });

    // Intersection Observer - animate when visible
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        cards.forEach(function (card) {
            observer.observe(card);
        });
    } else {
        cards.forEach(function (card) {
            card.classList.add('is-visible');
        });
    }

    // Title fade-in
    var title = document.querySelector('.dashboard-title-block');
    if (title) {
        title.classList.add('title-animate');
    }

    var headings = document.querySelectorAll('.section-heading');
    headings.forEach(function (el, i) {
        el.style.setProperty('--i', i);
        el.classList.add('heading-animate');
    });
})();
