document.addEventListener('DOMContentLoaded', () => {
    const scenes = Array.from(document.querySelectorAll('.ak-scene'));
    const pills = Array.from(document.querySelectorAll('.ak-step-pill'));
    const showcase = document.querySelector('.ak-showcase');

    if (!scenes.length || !pills.length) return;

    let index = 0;
    let timer = null;

    function showScene(nextIndex) {
        index = nextIndex;

        scenes.forEach((scene, i) => {
            scene.classList.toggle('is-active', i === index);
        });

        pills.forEach((pill, i) => {
            pill.classList.toggle('is-active', i === index);
        });
    }

    function startAutoPlay() {
        stopAutoPlay();
        timer = window.setInterval(() => {
            showScene((index + 1) % scenes.length);
        }, 5200);
    }

    function stopAutoPlay() {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    }

    pills.forEach((pill) => {
        pill.addEventListener('click', () => {
            const next = Number(pill.dataset.step || 0);
            showScene(next);
            startAutoPlay();
        });
    });

    if (showcase) {
        showcase.addEventListener('mouseenter', stopAutoPlay);
        showcase.addEventListener('mouseleave', startAutoPlay);
    }

    startAutoPlay();
});
