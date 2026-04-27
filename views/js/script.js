const parsePrice = (price) => {
  if (typeof price === "number") return Math.floor(price);
  return parseInt(String(price).replace(/[^0-9]/g, ""), 10) || 0;
};

const parseVariant = (attributes) => {
  if (!attributes) return "";
  if (typeof attributes === "string") return attributes;
  return Object.values(attributes).join(", ");
};

const sendEvent = (eventName, params) => {
  if (typeof gtag !== "function") return;
  gtag("event", eventName, params);
};

const sendPixelEvent = (eventName, params) => {
  if (typeof fbq !== "function") return;
  fbq("track", eventName, params);
};

const eventRemoveCart = (data, quantity) => {
  const item = {
    item_id: data.id_product,
    item_name: data.name,
    item_brand: data.itembrand,
    affiliation: "Tiendas AKA",
    price: parsePrice(data.price),
    quantity: quantity || 1,
    item_category: data.category || "",
    item_variant: parseVariant(data.attributes),
  };

  sendEvent("remove_from_cart", {
    currency: data.currency,
    value: item.price * item.quantity,
    items: [item],
  });
};

const getProductData = (idProduct, quantity) => {
  fetch(analyticsData.ajax_url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ product_id: idProduct }),
  })
    .then((response) => response.json())
    .then((data) => eventRemoveCart(data, quantity))
    .catch((error) => console.error("Error fetching product data:", error));
};

const eventCart = (prestashop) => {
  var items = [];
  var value = 0;

  prestashop.cart.products.forEach(function (product) {
    var price = parsePrice(product.price_wt);
    var quantity = parseInt(product.quantity);

    value += price * quantity;

    items.push({
      item_id: product.id_product,
      item_name: product.name,
      price: price,
      quantity: quantity,
      item_brand: product.manufacturer_name || "",
      item_variant: parseVariant(product.attributes),
      item_category: product.category || "",
    });
  });

  if (items.length === 0) return;

  sendEvent("view_cart", {
    currency: prestashop.currency.iso_code,
    value: value,
    items: items,
  });
};

const eventBeginCheckout = (prestashop) => {
  var items = [];
  var contentIds = [];
  var value = 0;

  prestashop.cart.products.forEach(function (product) {
    var price = parsePrice(product.price_wt);
    var quantity = parseInt(product.quantity);

    value += price * quantity;
    contentIds.push(String(product.id_product));

    items.push({
      item_id: product.id_product,
      item_name: product.name,
      price: price,
      quantity: quantity,
      affiliation: "Tiendas AKA",
      item_brand: product.manufacturer_name || "",
      item_variant: parseVariant(product.attributes),
      item_category: product.category || "",
    });
  });

  sendEvent("begin_checkout", {
    currency: prestashop.currency.iso_code,
    value: value,
    items: items,
  });

  sendPixelEvent("InitiateCheckout", {
    content_ids: contentIds,
    content_type: "product",
    num_items: items.length,
    value: value,
    currency: prestashop.currency.iso_code,
  });
};

const eventUpdateView = (data) => {
  const price = parsePrice(data.price);

  sendEvent("view_item", {
    currency: data.currency,
    value: price,
    items: [
      {
        item_id: data.id_product,
        item_name: data.name,
        item_brand: data.itembrand,
        item_category: data.category,
        item_variant: parseVariant(data.attributes),
        price: price,
      },
    ],
  });

  sendPixelEvent("ViewContent", {
    content_ids: [data.reference || String(data.id_product)],
    content_type: "product",
    value: price,
    currency: data.currency,
  });
};

const eventView_item = () => {
  const idProductEl = document.querySelector('input[name="id_product"]');
  if (!idProductEl) return;

  fetch(analyticsData.ajax_url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ product_id: idProductEl.value }),
  })
    .then((response) => response.json())
    .then((data) => eventUpdateView(data))
    .catch((error) => console.error("Error fetching product data:", error));
};

const eventAddToCart = (prestashop, event) => {
  const idProduct = event.reason.idProduct;
  const quantity = event.reason.quantity || 1;

  getProductDataForAdd(idProduct, quantity, prestashop.currency.iso_code);
};

const getProductDataForAdd = (idProduct, quantity, currency) => {
  fetch(analyticsData.ajax_url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ product_id: idProduct }),
  })
    .then((response) => response.json())
    .then((data) => {
      const price = parsePrice(data.price);
      const ecommerce = {
        currency: currency,
        value: price * quantity,
        items: [
          {
            item_id: data.id_product,
            item_name: data.name,
            item_brand: data.itembrand || "",
            affiliation: "Tiendas AKA",
            item_category: data.category || "",
            item_variant: parseVariant(data.attributes),
            price: price,
            quantity: quantity,
          },
        ],
      };

      sendEvent("add_to_cart", ecommerce);

      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({ ecommerce: null });
      window.dataLayer.push({ event: "add_to_cart", ecommerce: ecommerce });

      gtag("event", "conversion", {
        send_to: "AW-454489747/w6pdCJvgg5MCEJPt29gB",
        event_callback: ecommerce,
      });

      sendPixelEvent("AddToCart", {
        content_ids: [data.reference || String(data.id_product)],
        content_type: "product",
        value: price * quantity,
        currency: currency,
      });
    })
    .catch((error) => console.error("Error fetching product data:", error));
};

const getAnalyticsCookies = () => {
  const match = document.cookie.match(/_ga=GA\d\.\d\.(.+)/);
  return match ? match[1] : null;
};

const getSessionId = () => {
  const match = document.cookie.match(/_ga_09PSJYVMZX=GS\d+\.\d+\.s(\d+)/);
  return match ? match[1] : null;
};

const getSessionNumber = () => {
  const match = document.cookie.match(
    /_ga_09PSJYVMZX=GS\d+\.\d+\.s\d+\$o(\d+)/,
  );
  return match ? match[1] : null;
};

const getTotalProduct = () => {
 const totalProduct = document.querySelector('.total-products');

  if (!totalProduct) return;

  const text = totalProduct.textContent;
  const match = text.match(/\d+/);

  const total = match ? parseInt(match[0]) : 0;

  return total
}

document.addEventListener("DOMContentLoaded", function () {
  const clientId = getAnalyticsCookies();
  const sessionId = getSessionId();
  const sessionNumber = getSessionNumber();
  const totalProduct = getTotalProduct();
  if (clientId) {
    document.cookie = "ga_client_id= " + clientId + ";path=/";
  }

  if (sessionId) {
    document.cookie = "ga_session_id=" + sessionId + ";path=/";
  }

  if (sessionNumber) {
    document.cookie = "ga_session_number=" + sessionNumber + ";path=/";
  }

  if (typeof prestashop !== "undefined") {
    prestashop.on("updateCart", function (event) {
      if (event.reason.linkAction === "delete-from-cart") {
        const idProduct = event.reason.idProduct;
        const qtyEl = document.querySelector(
          `input.js-cart-line-product-quantity[data-product-id="${idProduct}"]`,
        );
        const quantity = qtyEl ? parseInt(qtyEl.value, 10) || 1 : 1;
        getProductData(idProduct, quantity);
      }
      if (event.reason.linkAction === "add-to-cart") {
        eventAddToCart(prestashop, event);
      }
    });

    if (prestashop.page.page_name === "cart") {
      eventCart(prestashop);
    }

    if (prestashop.page.page_name === "checkout") {
      eventBeginCheckout(prestashop);
    }

    if (prestashop.page.page_name === "product") {
      eventView_item();
    }

    if (prestashop.page.page_name === "search") {
      const params = new URLSearchParams(window.location.search);
      const searchTerm = params.get("s") || params.get("search_query") || "";
      if (searchTerm) {
        sendEvent("search", { search_term: searchTerm, results_count: totalProduct });
        sendPixelEvent("Search", { search_string: searchTerm, results_count: totalProduct });
      }
    }

    if (analyticsData.fire_login) {
      sendEvent("login", { method: "email" });
    }

    if (analyticsData.fire_sign_up) {
      sendEvent("sign_up", { method: "email" });
      sendPixelEvent("CompleteRegistration");
    }
  }
});
