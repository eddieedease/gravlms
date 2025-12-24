We have:
- angular frontend that i run locally (latest angular)
- a working tailwind installation
- backend using php and slimphp for api, this spins up via docker
- the backend and frontend are able to communicate with each other already
- mysql as database (also phpmyadmin is installed)
- This is an LMS we are building
- we already have logging in with JWT
- we have 3 hard roles. A 1)user 2) Editor and 3)Admin
- we have 1 roles that can be added to every of the three roles, an 1)monitor. This is a extra role that can see results of a certain group
- we have an editor where we can create courses and attach lessons/chapters
- we have components for users and admin, an editor en testresults
- users and groups can be connected to a course
- We have LTI implemented. Ver 1.1 and 1.3. As well for own consuming of external systems, as being able to launch our coursecontent in their LMS

