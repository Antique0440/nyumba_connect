# 🏠 Nyumba Connect Platform

A comprehensive mentorship and career development platform connecting students and alumni from Nyumba ya Watoto.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green.svg)

## ✨ Features

### 👥 **User Management**
- Role-based authentication (Students, Alumni, Admins)
- Secure registration and login system
- Profile management with education and skills tracking

### 🤝 **Mentorship System**
- Send and receive mentorship requests
- Accept/decline mentorship offers
- Track active mentorship relationships
- Mentorship history and analytics

### 💬 **Real-time Messaging**
- Secure communication between mentors and mentees
- Message threading and conversation history
- Unread message notifications
- Message status tracking

### 💼 **Opportunity Board**
- Job postings and internship opportunities
- Application management system
- CV upload and management
- Application tracking and status updates

### 📚 **Resource Library**
- Career development materials
- Document sharing and downloads
- Resource categorization
- Upload and management tools

### 🛡️ **Security Features**
- CSRF token protection
- SQL injection prevention
- XSS protection with input sanitization
- Session security and timeout
- Rate limiting for sensitive operations
- Secure file upload validation

## 🚀 Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework**: Bootstrap 5
- **Icons**: Bootstrap Icons
- **Security**: Multi-layered security implementation

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Modern web browser

## ⚡ Quick Start

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/nyumba-connect.git
cd nyumba-connect
```

### 2. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE nyumba_connect;"

# Import schema
mysql -u root -p nyumba_connect < sql/schema.sql

# Import sample data (optional)
mysql -u root -p nyumba_connect < sql/sample_data.sql
```

### 3. Configuration
1. Copy `includes/config.php` and update:
   ```php
   // Database Configuration
   define('DB_NAME', 'nyumba_connect');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   
   // Site Configuration
   define('SITE_URL', 'http://localhost/nyumba-connect');
   define('ADMIN_EMAIL', 'your-email@example.com');
   ```

2. Set file permissions:
   ```bash
   chmod 755 assets/uploads/
   chmod 755 assets/uploads/cvs/
   chmod 755 assets/uploads/resources/
   chmod 755 logs/
   chmod 755 sessions/
   ```

### 4. Access the Application
Visit `http://localhost/nyumba-connect` in your browser.

## 👤 Default Test Users

After importing sample data:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@nyumbaconnect.org | password |
| Alumni | john.kamau@email.com | password |
| Student | grace.akinyi@student.com | password |

## 📁 Project Structure

```
nyumba-connect/
├── 📁 admin/              # Admin dashboard and management
├── 📁 assets/             # CSS, JS, and uploaded files
│   ├── 📁 css/           # Stylesheets
│   ├── 📁 js/            # JavaScript files
│   └── 📁 uploads/       # User uploaded files
├── 📁 includes/           # Core PHP includes and configuration
├── 📁 logs/              # Application logs
├── 📁 mentorship/        # Mentorship management
├── 📁 messages/          # Messaging system
├── 📁 opportunities/     # Job/opportunity board
├── 📁 resources/         # Resource library
├── 📁 sessions/          # PHP session storage
├── 📁 sql/              # Database schema and sample data
└── 📄 *.php             # Main application pages
```

## 🔧 Development

### Local Development Setup
1. Use XAMPP, WAMP, or similar local server
2. Place project in web server directory
3. Configure database connection
4. Enable error reporting for debugging

### Key Configuration Files
- `includes/config.php` - Main configuration
- `includes/db.php` - Database connection
- `includes/auth.php` - Authentication functions
- `includes/security.php` - Security functions

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests if applicable
5. Commit your changes (`git commit -m 'Add some amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Coding Standards
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Ensure security best practices

## 🐛 Issues and Support

- **Bug Reports**: [Create an issue](https://github.com/yourusername/nyumba-connect/issues)
- **Feature Requests**: [Create an issue](https://github.com/yourusername/nyumba-connect/issues)
- **Questions**: [Discussions](https://github.com/yourusername/nyumba-connect/discussions)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Nyumba ya Watoto Community** - For inspiring this platform
- **Bootstrap Team** - For the excellent UI framework
- **PHP Community** - For the robust programming language
- **Contributors** - For making this project better

## 🌟 Star History

If you find this project useful, please consider giving it a star! ⭐

---

**Built with ❤️ for the Nyumba ya Watoto community**