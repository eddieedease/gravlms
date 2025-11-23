import { Component, signal } from '@angular/core';

interface Module {
  id: number;
  title: string;
  content: string;
}

@Component({
  selector: 'app-editor',
  imports: [],
  templateUrl: './editor.html',
  styleUrl: './editor.css',
})
export class Editor {
  modules: Module[] = [
    { id: 1, title: 'Introduction to Angular', content: 'Angular is a platform for building mobile and desktop web applications...' },
    { id: 2, title: 'Components & Templates', content: 'Components are the main building blocks for Angular applications...' },
    { id: 3, title: 'Dependency Injection', content: 'Dependency injection (DI) is a design pattern in which a class requests dependencies from external sources rather than creating them...' },
    { id: 4, title: 'Routing & Navigation', content: 'The Angular Router enables navigation from one view to the next as users perform application tasks...' },
  ];

  selectedModule = signal<Module | null>(this.modules[0]);
}
