import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ConfigService } from './config.service';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl: string;

  constructor(private http: HttpClient, private config: ConfigService) {
    this.apiUrl = this.config.apiUrl;
  }

  // Groups
  getGroups(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups`);
  }

  createGroup(group: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups`, group);
  }

  updateGroup(id: number, group: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/groups/${id}`, group);
  }

  deleteGroup(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${id}`);
  }

  getGroupUsers(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/users`);
  }

  addUserToGroup(groupId: number, userId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/users`, { user_id: userId });
  }

  removeUserFromGroup(groupId: number, userId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/users/${userId}`);
  }

  getGroupCourses(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/courses`);
  }

  addCourseToGroup(groupId: number, courseId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/courses`, { course_id: courseId });
  }

  removeCourseFromGroup(groupId: number, courseId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/courses/${courseId}`);
  }

  // Group Monitors
  getGroupMonitors(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/monitors`);
  }

  addMonitorToGroup(groupId: number, userId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/monitors`, { user_id: userId });
  }

  removeMonitorFromGroup(groupId: number, userId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/monitors/${userId}`);
  }

  // Results
  getResults(params: any = {}): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/results`, { params });
  }

  exportResults(params: any = {}): Observable<any> {
    return this.http.get(`${this.apiUrl}/results/export`, { params, responseType: 'text' });
  }

  // Email
  sendTestEmail(email: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/test-email`, { email });
  }

  getTestMessage(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/test`);
  }

  // LTI Management
  getLtiPlatforms(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/lti/platforms`);
  }

  createLtiPlatform(platform: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/admin/lti/platforms`, platform);
  }

  updateLtiPlatform(id: number, platform: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/admin/lti/platforms/${id}`, platform);
  }

  deleteLtiPlatform(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/admin/lti/platforms/${id}`);
  }

  getLtiTools(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/lti/tools`);
  }

  createLtiTool(tool: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/admin/lti/tools`, tool);
  }

  updateLtiTool(id: number, tool: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/admin/lti/tools/${id}`, tool);
  }

  deleteLtiTool(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/admin/lti/tools/${id}`);
  }

  getLtiConsumerLaunchParams(toolId: number, courseId?: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/lti/consumer/launch`, { tool_id: toolId, course_id: courseId });
  }
}
