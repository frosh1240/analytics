# 📊 Analytics Events Module

Módulo para la integración y gestión de eventos básicos de analítica en tu aplicación o e-commerce. Permite centralizar el envío de eventos clave hacia herramientas como Google Analytics, Meta Pixel u otros sistemas de tracking.

---

## 🚀 Características

- Configuración sencilla desde el panel de administración
- Soporte para eventos básicos:
  - Page View
  - View Item
  - Add to Cart
  - Purchase

- Integración con múltiples plataformas de analítica
- Activación/desactivación de eventos por configuración
- Código limpio y extensible

---

## 📦 Instalación

1. Clona este repositorio:

```bash
git clone https://github.com/tu-usuario/analytics-events-module.git
```

2. Copia el módulo en la carpeta correspondiente de tu proyecto:

```
/modules/analytics-events-module
```

3. Instala el módulo desde el panel de administración o mediante CLI según tu plataforma.

---

## ⚙️ Configuración

1. Accede al panel de administración
2. Busca el módulo **Analytics Events Module**
3. Configura los siguientes campos:

- ID de seguimiento (Google Analytics, Pixel, etc.)
- Eventos a activar
- Opciones adicionales según plataforma

---

## 🧠 Eventos soportados

| Evento         | Descripción                     |
| -------------- | ------------------------------- |
| Page View      | Se dispara al cargar una página |
| View Item      | Visualización de producto       |
| Add to Cart    | Producto agregado al carrito    |
| Search         | Búsquedas en el sitio web       |
| Begin checkout | Inicio de proceso de compra     |
| Purchase       | Compra completada               |

---

## 🧩 Uso

El módulo automáticamente inyecta los eventos en las páginas correspondientes según la configuración.

Ejemplo de evento personalizado:

```javascript
trackEvent("add_to_cart", {
  product_id: 123,
  value: 49.99,
  currency: "USD",
});
```

---

## 🛠️ Personalización

Puedes extender el módulo agregando nuevos eventos o integraciones:

- Edita los hooks correspondientes
- Agrega nuevos listeners en el frontend
- Integra APIs externas de tracking

---

## 🧪 Testing

- Verifica los eventos con herramientas como:
  - Google Tag Assistant
  - Meta Pixel Helper

- Revisa la consola del navegador para validar disparos

---

## 📁 Estructura del proyecto

```
analytics-events-module/
│
├── config/
├── controllers/
├── views/
├── analytics-events-module.php
└── README.md
```

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas:

1. Haz un fork del proyecto
2. Crea una rama (`feature/nueva-funcionalidad`)
3. Haz commit de tus cambios
4. Abre un Pull Request

---

## 📄 Licencia

Este proyecto está bajo la licencia MIT.

---

## 👨‍💻 Autor

Carlos Moreno
Desarrollador Web
