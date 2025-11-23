import { Component } from '@angular/core';

@Component({
  selector: 'app-admin',
  imports: [],
  templateUrl: './admin.html',
  styleUrl: './admin.css',
})
export class Admin {
  people = [
    { name: 'Lindsay Walton', title: 'Front-end Developer', email: 'lindsay.walton@example.com', role: 'Member', status: 'Active' },
    { name: 'Courtney Henry', title: 'Designer', email: 'courtney.henry@example.com', role: 'Admin', status: 'Active' },
    { name: 'Tom Cook', title: 'Director of Product', email: 'tom.cook@example.com', role: 'Member', status: 'Active' },
    { name: 'Whitney Francis', title: 'Copywriter', email: 'whitney.francis@example.com', role: 'Admin', status: 'Active' },
    { name: 'Leonard Krasner', title: 'Senior Designer', email: 'leonard.krasner@example.com', role: 'Owner', status: 'Active' },
    { name: 'Floyd Miles', title: 'Principal Designer', email: 'floyd.miles@example.com', role: 'Member', status: 'Active' },
  ];
}
