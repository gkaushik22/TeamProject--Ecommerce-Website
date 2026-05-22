// Wishlist functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize session storage for wishlist quantities if not exists
    if (!sessionStorage.getItem('wishlistQuantities')) {
        sessionStorage.setItem('wishlistQuantities', JSON.stringify({}));
    }
    
    // Update quantity display from session storage
    updateQuantityDisplayFromSession();
});

// Function to update wishlist item quantity
function updateWishlistQuantity(productId, change) {
    const inputElement = document.getElementById('quantity_wishlist_' + productId);
    if (!inputElement) return;
    
    const currentValue = parseInt(inputElement.value) || 1;
    const maxStock = parseInt(inputElement.getAttribute('max')) || 99;
    let newValue = currentValue + change;
    
    // Ensure value is within valid range
    if (newValue < 1) newValue = 1;
    if (newValue > maxStock) newValue = maxStock;
    
    // Update input value
    inputElement.value = newValue;
    
    // Store in session storage
    let quantities = JSON.parse(sessionStorage.getItem('wishlistQuantities') || '{}');
    quantities[productId] = newValue;
    sessionStorage.setItem('wishlistQuantities', JSON.stringify(quantities));
}

// Function to update quantity display from session storage
function updateQuantityDisplayFromSession() {
    const quantities = JSON.parse(sessionStorage.getItem('wishlistQuantities') || '{}');
    
    // Update all quantity inputs
    Object.keys(quantities).forEach(productId => {
        const inputElement = document.getElementById('quantity_wishlist_' + productId);
        if (inputElement) {
            const maxStock = parseInt(inputElement.getAttribute('max')) || 99;
            let value = quantities[productId];
            
            // Ensure value is within valid range
            if (value < 1) value = 1;
            if (value > maxStock) value = maxStock;
            
            inputElement.value = value;
        }
    });
}
