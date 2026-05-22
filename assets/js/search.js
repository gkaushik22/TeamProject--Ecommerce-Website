document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    let debounceTimeout;

    if (searchInput && suggestionsContainer) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length >= 2) { // Minimum 2 characters
                    fetchSuggestions(query);
                } else {
                    suggestionsContainer.innerHTML = '';
                    suggestionsContainer.classList.add('hidden');
                }
            }, 300); // Debounce delay
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.add('hidden');
            }
        });

        // Show suggestions on focus
        searchInput.addEventListener('focus', function () {
            if (this.value.trim().length >= 2) {
                fetchSuggestions(this.value.trim());
            }
        });
    }

    function fetchSuggestions(query) {
        fetch(`search_suggestions.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                suggestionsContainer.innerHTML = '';
                if (data.products.length > 0) {
                    data.products.forEach(product => {
                        const suggestionItem = document.createElement('a');
                        suggestionItem.href = `product_detail.php?id=${product.product_id}`;
                        suggestionItem.className = 'flex items-center p-2 hover:bg-gray-100 border-b border-gray-200';
                        suggestionItem.innerHTML = `
                            <img src="${product.image_path}" alt="${product.product_name}" class="w-12 h-12 object-cover rounded-md mr-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">${product.product_name}</p>
                                <p class="text-xs text-orange-500">$${product.price}</p>
                                <p class="text-xs text-gray-500">${product.shop_name}</p>
                                <p class="text-xs ${product.stock_available > 0 ? 'text-green-500' : 'text-red-500'}">
                                    ${product.stock_available > 0 ? `In Stock: ${product.stock_available}` : 'Out of Stock'}
                                </p>
                            </div>
                        `;
                        suggestionsContainer.appendChild(suggestionItem);
                    });
                    suggestionsContainer.classList.remove('hidden');
                } else {
                    suggestionsContainer.innerHTML = '<p class="p-2 text-sm text-gray-500">No products found</p>';
                    suggestionsContainer.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
                suggestionsContainer.innerHTML = '<p class="p-2 text-sm text-red-500">Error loading suggestions</p>';
                suggestionsContainer.classList.remove('hidden');
            });
    }
});