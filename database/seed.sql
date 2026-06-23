-- Seed Data for Crown Basketball Academy

-- Populate Training Days
INSERT INTO training_days (day_name) VALUES
('Senin'),
('Selasa'),
('Rabu'),
('Kamis'),
('Jumat'),
('Sabtu'),
('Minggu')
ON DUPLICATE KEY UPDATE day_name=VALUES(day_name);

-- Create Initial Admin User (password is 'deandra')
INSERT INTO users (username, email, password, role) VALUES
('deandra', 'cendekia@gmail.com', '$2y$10$v.5Es5kW2TQPABGrqGhbp.u.9hmoIIhmhvYi7QuCar3Ua2yNyYhCi', 'admin')
ON DUPLICATE KEY UPDATE username=username;

