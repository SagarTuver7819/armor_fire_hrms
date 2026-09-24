/**
 * Dashboard animations - hub tiles + department cards
 */
(function () {
    var cards = document.querySelectorAll('.module-card, .dash-hub-card');
    if (!cards.length) return;

    cards.forEach(function (card, index) {
        card.style.setProperty('--i', index);
        card.classList.add('card-animate');
    });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        cards.forEach(function (card) {
            observer.observe(card);
        });
    } else {
        cards.forEach(function (card) {
            card.classList.add('is-visible');
        });
    }

    var title = document.querySelector('.dashboard-title-block, .dash-hero');
    if (title) {
        title.classList.add('title-animate');
    }

    var headings = document.querySelectorAll('.section-heading');
    headings.forEach(function (el, i) {
        el.style.setProperty('--i', i);
        el.classList.add('heading-animate');
    });
})();
