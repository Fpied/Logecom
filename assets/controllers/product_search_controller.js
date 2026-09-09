import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "grid", "pagination"];

    connect() {
        this.products = Array.from(this.gridTarget.querySelectorAll(".product-card"));
        this.filteredProducts = this.products;
        this.currentPage = 1;
        this.productsPerPage = 6;
        this.updatePagination();
    }

    search() {
        const query = this.inputTarget.value.toLowerCase();
        this.filteredProducts = this.products.filter(product => product.dataset.name.includes(query));
        this.currentPage = 1; // Reset to first page on new search
        this.updatePagination();
    }

    updatePagination() {
        const totalPages = Math.ceil(this.filteredProducts.length / this.productsPerPage);
        const startIndex = (this.currentPage - 1) * this.productsPerPage;
        const endIndex = startIndex + this.productsPerPage;

        // Hide all products
        this.products.forEach(product => product.style.display = "none");

        // Show only the products 
        let filtreProduct = this.filteredProducts.slice(startIndex, endIndex);
        filtreProduct.forEach(products => products.style.display = "block");

        this.paginationTarget.innerHTML = "";
        for (let i = 1; i <= totalPages; i++){
            const btnPagination = document.createElement("button");
            btnPagination.classList.add("btnPagination");
            btnPagination.textContent = i;
            this.paginationTarget.appendChild(btnPagination);

            btnPagination.addEventListener("click", () => {
            this.currentPage = i;
            this.updatePagination();
        })
        }

        
    }
}