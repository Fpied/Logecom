

document.addEventListener("turbo:load", () => {
    const cart = document.querySelector(".cart");
    const featuredProduct = document.querySelector('.body');
    const cartHeight = cart.offsetHeight;
    featuredProduct.style.paddingBottom = cartHeight + "px";

})