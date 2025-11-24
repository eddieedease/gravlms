import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from './auth.service';

@Injectable({
    providedIn: 'root'
})
export class UserService {
    private apiUrl = 'http://localhost:8080/api/users';

    constructor(private http: HttpClient, private authService: AuthService) { }

    private getHeaders() {
        const token = this.authService.getToken();
        return new HttpHeaders({
            'Authorization': `Bearer ${token}`
        });
    }

    getUsers() {
        return this.http.get<any[]>(this.apiUrl, { headers: this.getHeaders() });
    }

    createUser(user: any) {
        return this.http.post<any>(this.apiUrl, user, { headers: this.getHeaders() });
    }

    updateUser(id: number, user: any) {
        return this.http.put<any>(`${this.apiUrl}/${id}`, user, { headers: this.getHeaders() });
    }

    deleteUser(id: number) {
        return this.http.delete<any>(`${this.apiUrl}/${id}`, { headers: this.getHeaders() });
    }
}
