document.addEventListener("turbo:load", () => {
    const eye = document.getElementById("eye");
    const passwordType = document.getElementById("inputPassword");

    if(eye !== null){
        eye.addEventListener("mousedown", (e) => {
        e.preventDefault();
        passwordType.type = "text";

        })

        eye.addEventListener("mouseup", (e) => {
            
            setTimeout(() => {
                passwordType.type = "password";

            }, 1000);
        })

    }
    

})
