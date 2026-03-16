document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute("href"));
    if (target) {
      target.scrollIntoView({ behavior: "smooth" });
    }
  });
});

const menuOverlay = document.querySelector('.menu-overlay');
const menuToggle = document.querySelector('.menu-toggle');
const menuClose = document.querySelector('.menu-close');
const menuLinks = document.querySelectorAll('.side-menu a');

function openMenu() {
  if (!menuOverlay || !menuToggle) return;
  menuOverlay.classList.add('active');
  menuOverlay.setAttribute('aria-hidden', 'false');
  menuToggle.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  if (!menuOverlay || !menuToggle) return;
  menuOverlay.classList.remove('active');
  menuOverlay.setAttribute('aria-hidden', 'true');
  menuToggle.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

if (menuToggle) {
  menuToggle.addEventListener('click', openMenu);
}

if (menuClose) {
  menuClose.addEventListener('click', closeMenu);
}

if (menuOverlay) {
  menuOverlay.addEventListener('click', (event) => {
    if (event.target === menuOverlay) {
      closeMenu();
    }
  });
}

menuLinks.forEach((link) => {
  link.addEventListener('click', closeMenu);
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    closeMenu();
  }
});

const form = document.querySelector("form");
const botao = document.getElementById("btnSend");

form.addEventListener("submit", function () {

    botao.innerText = "Email enviado!";
    botao.disabled = true;

});