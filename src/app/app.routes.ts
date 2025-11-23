import { Routes } from '@angular/router';
import { Landing } from './components/landing/landing';
import { Login } from './components/login/login';
import { Admin } from './components/admin/admin';
import { Editor } from './components/editor/editor';

export const routes: Routes = [
    { path: '', component: Landing },
    { path: 'login', component: Login },
    { path: 'admin', component: Admin },
    { path: 'editor', component: Editor },
    { path: '**', redirectTo: '' }
];
