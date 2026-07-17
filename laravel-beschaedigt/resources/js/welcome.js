document.addEventListener('alpine:init', () => {
    Alpine.data('welcomeHero', () => ({
        mobileOpen: false,
        activeScene: 0,
        timer: null,

        scenes: [
            { key: 'learning', label: 'MACHINE LEARNING PIPELINE' },
            { key: 'ai-engine', label: 'AKTIENKI AI ENGINE' },
            { key: 'market', label: 'LIVE MARKET INTELLIGENCE' },
            { key: 'ensemble', label: 'ADAPTIVE ENSEMBLE' },
            { key: 'decision', label: 'AI RECOMMENDATION' },
        ],

        models: [
            { name: 'Model 1', role: 'Champion', score: 92 },
            { name: 'Model 2', role: 'Runner-up', score: 88 },
            { name: 'Model 3', role: 'Challenger', score: 86 },
        ],

        get sceneLabel() {
            return this.scenes[this.activeScene].label
        },

        init() {
            this.startTimer()

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopTimer()
                } else {
                    this.startTimer()
                }
            })
        },

        startTimer() {
            this.stopTimer()

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return
            }

            this.timer = window.setInterval(() => {
                this.activeScene = (this.activeScene + 1) % this.scenes.length
            }, 5000)
        },

        stopTimer() {
            if (this.timer) {
                window.clearInterval(this.timer)
                this.timer = null
            }
        },

        setScene(index) {
            this.activeScene = index
            this.startTimer()
        },
    }))
})
