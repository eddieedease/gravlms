import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from './auth.service';
import { ConfigService } from './config.service';

@Injectable({
    providedIn: 'root'
})
export class GroupsService {
    private apiUrl: string;

    constructor(private http: HttpClient, private authService: AuthService, private config: ConfigService) {
        this.apiUrl = `${this.config.apiUrl}/groups`;
    }

    private getHeaders() {
        const token = this.authService.getToken();
        return new HttpHeaders({
            'Authorization': `Bearer ${token}`
        });
    }

    getGroups() {
        return this.http.get<any[]>(this.apiUrl, { headers: this.getHeaders() });
    }

    createGroup(group: any) {
        return this.http.post<any>(this.apiUrl, group, { headers: this.getHeaders() });
    }

    updateGroup(id: number, group: any) {
        return this.http.put<any>(`${this.apiUrl}/${id}`, group, { headers: this.getHeaders() });
    }

    deleteGroup(id: number) {
        return this.http.delete<any>(`${this.apiUrl}/${id}`, { headers: this.getHeaders() });
    }

    addUserToGroup(groupId: number, userId: number) {
        return this.http.post<any>(`${this.apiUrl}/${groupId}/users`, { user_id: userId }, { headers: this.getHeaders() });
    }

    removeUserFromGroup(groupId: number, userId: number) {
        return this.http.delete<any>(`${this.apiUrl}/${groupId}/users/${userId}`, { headers: this.getHeaders() });
    }

    getGroupUsers(groupId: number) {
        return this.http.get<any[]>(`${this.apiUrl}/${groupId}/users`, { headers: this.getHeaders() });
    }

    addCourseToGroup(groupId: number, courseId: number, validityDays?: number) {
        return this.http.post<any>(`${this.apiUrl}/${groupId}/courses`, { course_id: courseId, validity_days: validityDays }, { headers: this.getHeaders() });
    }

    removeCourseFromGroup(groupId: number, courseId: number) {
        return this.http.delete<any>(`${this.apiUrl}/${groupId}/courses/${courseId}`, { headers: this.getHeaders() });
    }

    getGroupCourses(groupId: number) {
        return this.http.get<any[]>(`${this.apiUrl}/${groupId}/courses`, { headers: this.getHeaders() });
    }
}
