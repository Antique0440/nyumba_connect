-- Sample Data for Nyumba Connect Platform
-- This file provides test data for development and testing purposes

-- Insert sample users
INSERT INTO users (name, email, password_hash, role, year_left, education, skills, location, bio, is_active) VALUES
-- Admin user
('System Admin', 'admin@nyumbaconnect.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 'Computer Science Degree', 'System Administration, Database Management', 'Nairobi, Kenya', 'System administrator for Nyumba Connect platform', TRUE),

-- Alumni users
('John Kamau', 'john.kamau@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni', 2018, 'Bachelor of Engineering - University of Nairobi', 'Software Development, Project Management, Leadership', 'Nairobi, Kenya', 'Software engineer with 5 years experience in fintech. Passionate about mentoring young developers.', TRUE),

('Mary Wanjiku', 'mary.wanjiku@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni', 2019, 'Bachelor of Business Administration - Strathmore University', 'Marketing, Business Development, Digital Marketing', 'Mombasa, Kenya', 'Marketing professional working in telecommunications. Love helping students navigate career choices.', TRUE),

('David Ochieng', 'david.ochieng@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'alumni', 2017, 'Bachelor of Medicine - University of Nairobi', 'Healthcare, Medical Research, Public Health', 'Kisumu, Kenya', 'Medical doctor specializing in public health. Interested in mentoring students in healthcare fields.', TRUE),

-- Student users
('Grace Akinyi', 'grace.akinyi@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL, 'Currently pursuing Computer Science - Technical University of Kenya', 'Programming, Web Development, Database Design', 'Nairobi, Kenya', 'Third-year computer science student passionate about web development and mobile apps.', TRUE),

('Peter Mwangi', 'peter.mwangi@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL, 'Currently pursuing Business Administration - Kenyatta University', 'Business Analysis, Communication, Leadership', 'Nakuru, Kenya', 'Second-year business student interested in entrepreneurship and business development.', TRUE),

('Sarah Njeri', 'sarah.njeri@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL, 'Currently pursuing Nursing - Kenya Medical University', 'Patient Care, Medical Procedures, Health Education', 'Eldoret, Kenya', 'First-year nursing student with passion for community health and patient care.', TRUE);

-- Insert sample opportunities
INSERT INTO opportunities (title, description, category, deadline, link, posted_by, status) VALUES
('Software Developer Internship', 'Join our development team for a 6-month internship program. Work on real projects using modern technologies including React, Node.js, and MongoDB. Mentorship provided.', 'Technology', '2025-02-15', 'https://company.com/internship', 2, 'approved'),

('Marketing Assistant Position', 'Entry-level marketing position with growth opportunities. Responsibilities include social media management, content creation, and market research.', 'Marketing', '2025-01-30', 'https://marketing-company.com/jobs', 3, 'approved'),

('Medical Research Scholarship', 'Full scholarship for medical students interested in public health research. Includes stipend and research mentorship.', 'Healthcare', '2025-03-01', 'https://research-institute.org/scholarship', 4, 'pending'),

('Junior Business Analyst Role', 'Great opportunity for business students to gain experience in data analysis and business intelligence. Training provided.', 'Business', '2025-02-28', 'https://consulting-firm.com/careers', 2, 'approved');

-- Insert sample applications
INSERT INTO applications (opportunity_id, applicant_id, cv_path, cover_letter, status) VALUES
(1, 5, '/assets/uploads/cvs/grace_akinyi_cv.pdf', 'I am very interested in this software development internship as it aligns perfectly with my computer science studies and passion for web development. I have experience with JavaScript and am eager to learn React and Node.js.', 'applied'),

(2, 6, '/assets/uploads/cvs/peter_mwangi_cv.pdf', 'This marketing assistant position would be an excellent opportunity for me to apply my business administration knowledge in a practical setting. I am particularly interested in digital marketing and social media management.', 'applied'),

(4, 6, '/assets/uploads/cvs/peter_mwangi_cv.pdf', 'As a business administration student, I am very interested in gaining experience in business analysis. I have strong analytical skills and am proficient in Excel and data analysis tools.', 'applied');

-- Insert sample mentorship requests
INSERT INTO mentorship_requests (student_id, alumni_id, message, status) VALUES
(5, 2, 'Hi John, I am Grace, a computer science student. I would love to learn from your experience in software development and get guidance on career paths in tech. Would you be willing to mentor me?', 'accepted'),

(6, 3, 'Hello Mary, I am Peter, studying business administration. I am very interested in marketing and would appreciate your mentorship to help me understand the industry better and make informed career decisions.', 'pending'),

(7, 4, 'Dear Dr. David, I am Sarah, a nursing student. I am interested in public health and would be honored to learn from your experience in the medical field. Could you please consider mentoring me?', 'pending');

-- Insert sample mentorships (for accepted requests)
INSERT INTO mentorships (student_id, alumni_id, active) VALUES
(5, 2, TRUE);

-- Insert sample messages
INSERT INTO messages (sender_id, receiver_id, mentorship_id, message_text, is_read) VALUES
(5, 2, 1, 'Thank you so much for accepting my mentorship request! I am really excited to learn from you.', TRUE),
(2, 5, 1, 'You are very welcome, Grace! I am happy to help. Let us start by discussing your current projects and career goals.', TRUE),
(5, 2, 1, 'I am currently working on a web application project using HTML, CSS, and JavaScript. I would love to get your feedback on my approach.', FALSE),
(2, 5, 1, 'That sounds great! Feel free to share your code repository with me and I will review it. Also, have you considered learning a framework like React?', FALSE);

-- Insert sample resources
INSERT INTO resources (title, file_path, uploaded_by, download_count) VALUES
('CV Writing Guide for Students', '/assets/uploads/resources/cv_writing_guide.pdf', 1, 15),
('Interview Preparation Checklist', '/assets/uploads/resources/interview_prep_checklist.pdf', 1, 23),
('Career Planning Workbook', '/assets/uploads/resources/career_planning_workbook.pdf', 1, 8),
('Scholarship Application Tips', '/assets/uploads/resources/scholarship_tips.pdf', 1, 12),
('Professional Email Writing Guide', '/assets/uploads/resources/email_writing_guide.pdf', 1, 19);