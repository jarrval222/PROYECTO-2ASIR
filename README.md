# 🛒 Plataforma Web Interna de Gestión

Proyecto de 2º de ASIR  
Desarrollo de una intranet para la gestión de usuarios, incidencias, inventario y nóminas dentro de una organización simulada.

---

## 📋 Descripción del proyecto

El objetivo de este proyecto es crear una **plataforma web interna** que permita la gestión centralizada de distintos procesos administrativos (usuarios, incidencias, inventario, nóminas, etc.), desplegada sobre un **servidor Linux** con servicios web y base de datos.

El sistema está diseñado para un entorno empresarial cerrado, con acceso seguro mediante autenticación LDAP/AD y conexión HTTPS.

---

## 🧱 Arquitectura del sistema

**Componentes principales:**

- 🖥️ **Servidor Web:** Apache o Nginx (Linux)
- 🗄️ **Base de Datos:** MySQL / MariaDB / PostgreSQL
- 🧩 **Backend:** PHP / Flask / Django
- 🔐 **Autenticación:** LDAP o Active Directory
- 💾 **Copias de seguridad automáticas:** Scripts Bash (cron)
- 🧰 **Seguridad:** HTTPS, firewall UFW, permisos de acceso

**Esquema general:**

