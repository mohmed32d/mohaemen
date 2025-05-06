if (typeof CyberParticles === 'undefined') {
    class CyberParticles {
        constructor(canvasId) {
            this.canvas = document.getElementById(canvasId);
            this.ctx = this.canvas.getContext('2d');
            this.particles = [];
            this.symbols = ['{}', '[]', '()', '<>', '//', '/*', '*/', '=>', ';', '#', '$'];
            
            this.init();
        }

        init() {
            this.resizeCanvas();
            window.addEventListener('resize', () => this.resizeCanvas());
            this.createParticles();
            this.animate();
        }

        resizeCanvas() {
            this.canvas.width = window.innerWidth;
            this.canvas.height = window.innerHeight;
        }

        createParticles() {
            for (let i = 0; i < 50; i++) {
                this.particles.push({
                    x: Math.random() * this.canvas.width,
                    y: Math.random() * this.canvas.height,
                    size: Math.random() * 20 + 10,
                    speed: Math.random() * 2 + 1,
                    symbol: this.symbols[Math.floor(Math.random() * this.symbols.length)],
                    opacity: Math.random() * 0.5 + 0.1
                });
            }
        }

        animate() {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.particles.forEach(p => {
                p.y += p.speed;
                if (p.y > this.canvas.height) {
                    p.y = -20;
                    p.x = Math.random() * this.canvas.width;
                }

                this.ctx.fillStyle = `rgba(100, 255, 150, ${p.opacity})`;
                this.ctx.font = `${p.size}px 'Courier New', monospace`;
                this.ctx.fillText(p.symbol, p.x, p.y);
            });

            requestAnimationFrame(() => this.animate());
        }
    }
}
