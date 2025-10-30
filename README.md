# Social Network MongoDB

A MongoDB-based social network application with user management, posts, comments, likes, and follow relationships.

## Database Setup

### Prerequisites

- Node.js (v14 or higher)
- MongoDB (v4.0 or higher)

### Installation

1. Install dependencies:
```bash
npm install
```

### Running the Migration

The migration script will create all necessary collections and indexes for the social network application.

#### Default Configuration

By default, the migration connects to:
- **MongoDB URL**: `mongodb://localhost:27017`
- **Database Name**: `social_network`

Run the migration:
```bash
npm run migrate
```

#### Custom Configuration

You can customize the MongoDB connection using environment variables:

```bash
MONGO_URL=mongodb://your-mongodb-url:27017 DB_NAME=your_database_name npm run migrate
```

### Database Schema

The migration creates the following collections:

#### 1. Users
- `pseudo` (string, required): User's pseudonym
- `email` (string, required, unique): User's email address
- `bio` (string, optional): User biography
- `createdAt` (date, required): Account creation timestamp

#### 2. Categories
- `name` (string, required, unique): Category name
- `description` (string, optional): Category description
- `createdAt` (date, required): Category creation timestamp

#### 3. Posts
- `userId` (ObjectId, required): Reference to the user who created the post
- `categoryId` (ObjectId, optional): Reference to the post category
- `content` (string, required): Post content
- `createdAt` (date, required): Post creation timestamp
- `updatedAt` (date, optional): Post last update timestamp

#### 4. Comments
- `postId` (ObjectId, required): Reference to the post
- `userId` (ObjectId, required): Reference to the user who created the comment
- `content` (string, required): Comment content
- `createdAt` (date, required): Comment creation timestamp
- `updatedAt` (date, optional): Comment last update timestamp

#### 5. Likes
- `postId` (ObjectId, required): Reference to the liked post
- `userId` (ObjectId, required): Reference to the user who liked
- `createdAt` (date, required): Like creation timestamp
- **Unique constraint**: A user can only like a post once

#### 6. Follows
- `followerId` (ObjectId, required): Reference to the user who follows
- `followedId` (ObjectId, required): Reference to the user being followed
- `createdAt` (date, required): Follow relationship creation timestamp
- **Unique constraint**: A user can only follow another user once

### Indexes

The migration creates the following indexes for performance optimization:

- **Users**: 
  - Unique index on `email`
  - Index on `pseudo`
  
- **Categories**: 
  - Unique index on `name`
  
- **Posts**: 
  - Index on `userId`
  - Index on `categoryId`
  - Descending index on `createdAt`
  
- **Comments**: 
  - Index on `postId`
  - Index on `userId`
  - Descending index on `createdAt`
  
- **Likes**: 
  - Compound unique index on `postId` and `userId`
  - Index on `userId`
  
- **Follows**: 
  - Compound unique index on `followerId` and `followedId`
  - Index on `followedId`

### Re-running the Migration

The migration script is idempotent and can be run multiple times safely. If collections already exist, the script will skip their creation while still ensuring indexes are in place.

## API Endpoints (To Be Implemented)

Based on the project requirements, the following API endpoints should be implemented:

### Users
- `GET /users` - Get all users
- `GET /users/{id}` - Get a user by ID
- `POST /users` - Create a new user
- `PUT /users/{id}` - Update a user
- `DELETE /users/{id}` - Delete a user
- `GET /users/pseudos?page={page}&limit=3` - Get user pseudonyms with pagination
- `GET /users/{id}/followers/count` - Get follower count
- `GET /users/{id}/following/count` - Get following count
- `GET /users/most-followed?limit=3` - Get most followed users

### Posts
- `GET /posts` - Get all posts
- `GET /posts/{id}` - Get a post by ID
- `POST /posts` - Create a new post
- `PUT /posts/{id}` - Update a post
- `DELETE /posts/{id}` - Delete a post
- `GET /posts/latest?limit=5` - Get latest posts
- `GET /posts/{id}/details` - Get post with comments
- `GET /posts/no-comments` - Get posts without comments
- `GET /posts/search?query={keyword}` - Search posts by keyword
- `GET /posts/before?date={YYYY-MM-DD}` - Get posts before a date
- `GET /posts/after?date={YYYY-MM-DD}` - Get posts after a date

### Categories
- `GET /categories` - Get all categories
- `GET /categories/{id}` - Get a category by ID
- `POST /categories` - Create a new category
- `PUT /categories/{id}` - Update a category
- `DELETE /categories/{id}` - Delete a category

### Comments
- `GET /comments` - Get all comments
- `GET /comments/{id}` - Get a comment by ID
- `POST /comments` - Create a new comment
- `PUT /comments/{id}` - Update a comment
- `DELETE /comments/{id}` - Delete a comment

### Likes
- `GET /likes` - Get all likes
- `POST /likes` - Create a new like
- `DELETE /likes/{id}` - Delete a like

### Follows
- `GET /follows` - Get all follows
- `POST /follows` - Create a new follow relationship
- `DELETE /follows/{id}` - Delete a follow relationship

### Statistics
- `GET /stats/users/count` - Get total user count
- `GET /stats/posts/count` - Get total post count
- `GET /stats/posts/{id}/comments/count` - Get comment count for a post
- `GET /stats/categories/{id}/likes/average` - Get average likes per category

## License

ISC
