# Nyumba Connect Platform

A comprehensive mentorship and career development platform connecting students and alumni from Nyumba ya Watoto.

## Features

- **User Management**: Role-based authentication (Students, Alumni, Admins)
- **Mentorship System**: Request, accept, and manage mentorship relationships
- **Real-time Messaging**: Secure communication between mentors and mentees
- **Opportunity Board**: Job postings, internships, and career opportunities
- **Resource Library**: Career development materials and guides
- **CV Management**: Upload and manage CVs for applications
- **Admin Dashboard**: Comprehensive administration tools

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework**: Bootstrap 5
- **Icons**: Bootstrap Icons
- **Security**: CSRF protection, SQL injection prevention, XSS protection

## Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Composer (for dependency management)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Antique0440/nyumba_connect.git
   cd nyumba_connect
   ```

2. **Database Setup**
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE nyumba_connect;"
   
   # Import schema
   mysql -u root -p nyumba_connect < nyumba_connect/sql/schema.sql
   
   # Import sample data (optional)
   mysql -u root -p nyumba_connect < nyumba_connect/sql/sample_data.sql
   ```

3. **Configuration**
   - Update database credentials in `nyumba_connect/includes/config.php`
   - Set appropriate file permissions for upload directories
   - Configure your web server to point to the `nyumba_connect` directory

4. **File Permissions**
   ```bash
   chmod 755 nyumba_connect/assets/uploads/
   chmod 755 nyumba_connect/assets/uploads/cvs/
   chmod 755 nyumba_connect/assets/uploads/resources/
   chmod 644 nyumba_connect/logs/
   ```

## Default Users

After importing sample data, you can log in with:

- **Admin**: admin@nyumbaconnect.org / password
- **Alumni**: john.kamau@email.com / password  
- **Student**: grace.akinyi@student.com / password

## Project Structure

```
nyumba_connect/
├── admin/              # Admin dashboard and management
├── assets/             # CSS, JS, and uploaded files
├── includes/           # Core PHP includes and configuration
├── logs/              # Application logs
├── mentorship/        # Mentorship management
├── messages/          # Messaging system
├── opportunities/     # Job/opportunity board
├── resources/         # Resource library
├── sessions/          # PHP session storage
├── sql/              # Database schema and sample data
└── *.php             # Main application pages
```

## Security Features

- CSRF token protection
- SQL injection prevention
- XSS protection with input sanitization
- Session security and timeout
- Rate limiting for sensitive operations
- Secure file upload validation
- Role-based access control

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Built for Nyumba ya Watoto community
- Designed to foster mentorship and career development
- Inspired by the need to connect students with successful alumni

## Support

For support, email support@nyumbaconnect.org or create an issue in this repository.