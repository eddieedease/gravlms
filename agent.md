We have:
- angular frontend that i run locally (latest angular)
- backend using php and slimphp for api, this spins up via docker
- the backend and frontend are able to communicate with each other already
- mysql as database (also phpmyadmin is installed)
- This is an LMS we are building
- we already have logging in with JWT
- we have an editor where we can create courses and attach lessons/chapters
- we have components for users and admin
- users and groups can be connected to a course

I want to be to add tests to the e-learning courses. Right now there are only pages. I want to be able to add a test-item to chapter where the questions consist of multiple choise questions.

For this, we also need to add table to the database. Please make this and insert the inititializon of it in the html-> init.db file

Also for the backend API (slimphp v4) we need to create another route with crud operations I think.
