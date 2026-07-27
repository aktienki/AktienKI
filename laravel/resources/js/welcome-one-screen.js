document.addEventListener('DOMContentLoaded', () => {
    const scenes = [...document.querySelectorAll('.ak-scene-svg')];
    const copies = [...document.querySelectorAll('.ak-scene-copy')];
    const dots = [...document.querySelectorAll('.ak-dots button')];
    const prev = document.querySelector('.ak-slider-prev');
    const next = document.querySelector('.ak-slider-next');
    const visual = document.querySelector('.ak-desktop-wrap');

    if (!scenes.length) return;

    let index = 0;
    let interval = null;

    function showScene(nextIndex) {
        index = (nextIndex + scenes.length) % scenes.length;
        scenes.forEach((scene, i) => scene.classList.toggle('is-active', i === index));
        copies.forEach((copy, i) => copy.classList.toggle('is-active', i === index));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    }

    function start() {
        stop();
        interval = window.setInterval(() => showScene(index + 1), 4800);
    }

    function stop() {
        if (interval) window.clearInterval(interval);
        interval = null;
    }

    next?.addEventListener('click', () => {
        showScene(index + 1);
        start();
    });

    prev?.addEventListener('click', () => {
        showScene(index - 1);
        start();
    });

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            showScene(Number(dot.dataset.dot || 0));
            start();
        });
    });

    visual?.addEventListener('mouseenter', stop);
    visual?.addEventListener('mouseleave', start);

    start();
});
